import asyncio
import os

import telnetlib3

HOST = os.getenv("JASMIN_CLI_HOST", "127.0.0.1")
PORT = int(os.getenv("JASMIN_CLI_PORT", "8990"))
CLI_USER = os.getenv("JASMIN_CLI_USER", "jcliadmin")
CLI_PASSWORD = os.getenv("JASMIN_CLI_PASSWORD", "jclipwd")
API_UID = os.getenv("JASMIN_API_UID", "laravel_api")
API_USERNAME = os.getenv("JASMIN_USERNAME", "laravel_api")
API_PASSWORD = os.getenv("JASMIN_PASSWORD", "laravel_secret")


async def read_until(reader, markers, timeout=20):
    if isinstance(markers, (bytes, str)):
        markers = [markers]
    markers = [m.decode() if isinstance(m, bytes) else m for m in markers]
    data = ""
    while not any(marker in data for marker in markers):
        chunk = await asyncio.wait_for(reader.read(1024), timeout=timeout)
        if not chunk:
            raise RuntimeError("Jasmin CLI closed the connection")
        data += chunk
    return data


async def command(reader, writer, value):
    writer.write(value + "\n")
    await writer.drain()
    return await read_until(reader, "jcli : ")


async def provision():
    reader, writer = await telnetlib3.open_connection(HOST, PORT, encoding="utf-8")
    try:
        first_prompt = await read_until(reader, ["Username: ", "jcli : "])
        if "Username: " in first_prompt:
            writer.write(CLI_USER + "\n")
            await writer.drain()
            await read_until(reader, "Password: ")
            writer.write(CLI_PASSWORD + "\n")
            await writer.drain()
            await read_until(reader, "jcli : ")

        groups = await command(reader, writer, "group -l")
        if API_UID not in groups:
            await command(reader, writer, "group -a")
            await command(reader, writer, "gid laravel")
            await command(reader, writer, "ok")

        users = await command(reader, writer, "user -l")
        if API_UID not in users:
            await command(reader, writer, "user -a")
            await command(reader, writer, "gid laravel")
            await command(reader, writer, f"uid {API_UID}")
            await command(reader, writer, f"username {API_USERNAME}")
            await command(reader, writer, f"password {API_PASSWORD}")
            await command(reader, writer, "mt_messaging_cred authorization http_send True")
            await command(reader, writer, "mt_messaging_cred authorization http_balance True")
            await command(reader, writer, "mt_messaging_cred authorization http_rate True")
            await command(reader, writer, "mt_messaging_cred authorization dlr_level True")
            await command(reader, writer, "mt_messaging_cred authorization http_dlr_method True")
            await command(reader, writer, "mt_messaging_cred quota balance 100000")
            await command(reader, writer, "mt_messaging_cred quota submit_sm_count 100000")
            await command(reader, writer, "ok")
    finally:
        writer.close()


asyncio.run(provision())
print(f"Jasmin default HTTP user ready: {API_USERNAME}")
