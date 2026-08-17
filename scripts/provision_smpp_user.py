#!/usr/bin/env python3
import argparse
import asyncio
import os
import telnetlib3


async def read_until(reader, prompts, timeout=15):
    if isinstance(prompts, str):
        prompts = [prompts]
    data = ''
    while True:
        chunk = await asyncio.wait_for(reader.read(1024), timeout=timeout)
        if not chunk:
            raise RuntimeError('Jasmin jcli closed the connection')
        data += chunk
        if any(prompt in data for prompt in prompts):
            return data


def fail_if_error(output):
    lowered = output.lower()
    if 'error:' in lowered or 'unknown' in lowered:
        raise RuntimeError(output.strip())


async def send(reader, writer, value, prompts=('jcli : ', '> ')):
    writer.write(value + '\n')
    await writer.drain()
    output = await read_until(reader, prompts)
    fail_if_error(output)
    return output


async def login(args):
    reader, writer = await telnetlib3.open_connection(args.host, args.port, encoding='utf-8')
    first = await read_until(reader, ['Username:', 'jcli : '])
    if 'Username:' in first:
        writer.write(args.cli_username + '\n')
        await writer.drain()
        await read_until(reader, ['Password:'])
        writer.write(args.cli_password + '\n')
        await writer.drain()
        await read_until(reader, ['jcli : '])
    return reader, writer


async def provision(args):
    reader, writer = await login(args)
    try:
        await send(reader, writer, 'group -a')
        await send(reader, writer, 'gid ' + args.gid)
        writer.write('ok\n')
        await writer.drain()
        group_saved = await read_until(reader, ['jcli : ', '> '])
        if 'already exists' not in group_saved.lower():
            fail_if_error(group_saved)

        await send(reader, writer, 'user -a')
        await send(reader, writer, 'gid ' + args.gid)
        await send(reader, writer, 'uid ' + args.uid)
        await send(reader, writer, 'username ' + args.username)
        await send(reader, writer, 'password ' + args.password)
        await send(reader, writer, 'smpps_cred quota max_bindings ' + str(args.max_bind))
        await send(reader, writer, 'smpps_cred authorization bind yes')
        writer.write('ok\n')
        await writer.drain()
        saved = await read_until(reader, ['jcli : '])
        fail_if_error(saved)
        print('PROVISIONED')
    finally:
        writer.close()
        try:
            await writer.wait_closed()
        except Exception:
            pass


async def update_user(args):
    reader, writer = await login(args)
    try:
        await send(reader, writer, 'user -u ' + args.uid)
        if args.username:
            await send(reader, writer, 'username ' + args.username)
        if args.password:
            await send(reader, writer, 'password ' + args.password)
        if args.max_bind is not None:
            await send(reader, writer, 'smpps_cred quota max_bindings ' + str(args.max_bind))
        writer.write('ok\n')
        await writer.drain()
        saved = await read_until(reader, ['jcli : '])
        fail_if_error(saved)
        print('UPDATED')
    finally:
        writer.close()
        try:
            await writer.wait_closed()
        except Exception:
            pass


async def set_enabled(args, enabled):
    reader, writer = await login(args)
    try:
        command = ('user -e ' if enabled else 'user -d ') + args.uid
        output = await send(reader, writer, command, prompts=('jcli : ',))
        fail_if_error(output)
        print('ENABLED' if enabled else 'DISABLED')
    finally:
        writer.close()
        try:
            await writer.wait_closed()
        except Exception:
            pass


async def show_user(args):
    reader, writer = await login(args)
    try:
        output = await send(reader, writer, 'user -s ' + args.uid, prompts=('jcli : ',))
        fail_if_error(output)
        print(output)
    finally:
        writer.close()
        try:
            await writer.wait_closed()
        except Exception:
            pass


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('action', choices=['provision', 'update', 'disable', 'enable', 'show'])
    parser.add_argument('--uid', required=True)
    parser.add_argument('--gid', default=os.getenv('JASMIN_GROUP_ID', 'laravelcustomers'))
    parser.add_argument('--username')
    parser.add_argument('--password')
    parser.add_argument('--max-bind', type=int, default=1)
    parser.add_argument('--host', default=os.getenv('JASMIN_CLI_HOST', 'jasmin'))
    parser.add_argument('--port', type=int, default=int(os.getenv('JASMIN_CLI_PORT', '8990')))
    parser.add_argument('--cli-username', default=os.getenv('JASMIN_CLI_USERNAME', 'jcliadmin'))
    parser.add_argument('--cli-password', default=os.getenv('JASMIN_CLI_PASSWORD', 'jclipwd'))
    args = parser.parse_args()

    if args.action == 'provision' and (not args.username or not args.password):
        parser.error('provision requires --username and --password')
    if args.action == 'update' and not any([args.username, args.password, args.max_bind is not None]):
        parser.error('update requires --username, --password or --max-bind')

    if args.action == 'provision':
        asyncio.run(provision(args))
    elif args.action == 'update':
        asyncio.run(update_user(args))
    elif args.action == 'disable':
        asyncio.run(set_enabled(args, False))
    elif args.action == 'enable':
        asyncio.run(set_enabled(args, True))
    else:
        asyncio.run(show_user(args))
