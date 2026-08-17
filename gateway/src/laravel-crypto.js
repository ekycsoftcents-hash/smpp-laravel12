import crypto from 'node:crypto';

function appKey() {
  const raw = process.env.APP_KEY || '';
  const value = raw.startsWith('base64:') ? Buffer.from(raw.slice(7), 'base64') : Buffer.from(raw);
  if (value.length !== 32) throw new Error('APP_KEY must be a 32-byte Laravel key or base64: key');
  return value;
}

export function decryptLaravel(value) {
  if (!value) return value;
  try {
    const payload = JSON.parse(Buffer.from(value, 'base64').toString('utf8'));
    const iv = Buffer.from(payload.iv, 'base64');
    const decipher = crypto.createDecipheriv('aes-256-cbc', appKey(), iv);
    const plain = Buffer.concat([decipher.update(Buffer.from(payload.value, 'base64')), decipher.final()]).toString('utf8');
    const serialized = Buffer.from(plain, 'base64').toString('utf8');
    const match = serialized.match(/^s:\d+:"([\s\S]*)";$/);
    return match ? match[1] : serialized;
  } catch {
    return value;
  }
}
