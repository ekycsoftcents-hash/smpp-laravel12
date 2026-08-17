import smpp from 'smpp';
import { randomUUID } from 'node:crypto';
import config from './config.js';
import { getClientAccount, publishEvent, redis, saveMessage, setBindState } from './store.js';

function decodeMessage(pdu) {
  return typeof pdu.short_message === 'string' ? pdu.short_message : (pdu.short_message?.message ?? '');
}

export function startClientServer({ submit }) {
  const server = smpp.createServer({
    host: config.SMPP_CLIENT_BIND_HOST,
    port: config.SMPP_CLIENT_BIND_PORT,
    debug: false
  }, (session) => {
    let account = null;
    const bindKey = () => account ? `client:${account.system_id}:${session.socket.remoteAddress}` : null;

    session.on('bind_transceiver', async (pdu) => {
      try {
        account = await getClientAccount(pdu.system_id);
        const valid = account && pdu.password === account.password;
        if (!valid) {
          session.send(pdu.response({ command_status: smpp.ESME_RINVSYSID }));
          session.close();
          return;
        }
        const activeKey = `reve:binds:${account.system_id}`;
        const binds = Number(await redis.get(activeKey) || 0);
        if (binds >= Number(account.max_bind || 1)) {
          session.send(pdu.response({ command_status: smpp.ESME_RBINDFAIL }));
          session.close();
          return;
        }
        await redis.incr(activeKey);
        session.send(pdu.response({ system_id: 'reve-gateway' }));
        await setBindState(bindKey(), { system_id: account.system_id, customer_id: account.customer_id, state: 'BOUND', ip: session.socket.remoteAddress, bound_at: new Date().toISOString() });
        await publishEvent('client.bind', { system_id: account.system_id, customer_id: account.customer_id, ip: session.socket.remoteAddress });
      } catch (error) {
        session.send(pdu.response({ command_status: smpp.ESME_RSYSERR }));
        session.close();
      }
    });

    session.on('submit_sm', async (pdu) => {
      if (!account) return session.send(pdu.response({ command_status: smpp.ESME_RINVBNDSTS }));
      const messageId = randomUUID();
      const source = pdu.source_addr || account.system_id;
      const destination = pdu.destination_addr;
      const text = decodeMessage(pdu);
      const segments = Math.max(1, Math.ceil(Buffer.byteLength(text, 'utf8') / 140));
      const message = await saveMessage({
        messageId, customerId: account.customer_id, source, destination, text, segments,
        currency: account.currency || 'BDT',
        metadata: JSON.stringify({ source: 'smpp-client', system_id: account.system_id, esm_class: pdu.esm_class, data_coding: pdu.data_coding })
      });
      session.send(pdu.response({ message_id: messageId }));
      await submit({ message, account, session });
    });

    session.on('enquire_link', (pdu) => session.send(pdu.response()));
    session.on('unbind', (pdu) => { session.send(pdu.response()); session.close(); });
    session.on('close', async () => {
      if (!account) return;
      const activeKey = `reve:binds:${account.system_id}`;
      await redis.decr(activeKey).catch(() => undefined);
      await setBindState(bindKey(), { system_id: account.system_id, customer_id: account.customer_id, state: 'CLOSED', closed_at: new Date().toISOString() }, 60);
      await publishEvent('client.unbind', { system_id: account.system_id, customer_id: account.customer_id });
    });
    session.on('error', (error) => publishEvent('client.error', { system_id: account?.system_id, error: error.message }));
  });
  server.listen(config.SMPP_CLIENT_BIND_PORT, config.SMPP_CLIENT_BIND_HOST);
  return server;
}
