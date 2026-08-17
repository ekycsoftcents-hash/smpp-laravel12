import os
import socket
import time

HOST = os.getenv("JASMIN_CLI_HOST", "127.0.0.1")
PORT = int(os.getenv("JASMIN_CLI_PORT", "8990"))
CLI_USER = os.getenv("JASMIN_CLI_USER", "jcliadmin")
CLI_PASSWORD = os.getenv("JASMIN_CLI_PASSWORD", "jclipwd")
API_UID = os.getenv("JASMIN_API_UID", "laravel_api")
API_USERNAME = os.getenv("JASMIN_USERNAME", "laravel_api")
API_PASSWORD = os.getenv("JASMIN_PASSWORD", "laravel_secret")


def read_until(sock, marker, timeout=15):
    sock.settimeout(timeout)
    data = b""
    while marker not in data:
        chunk = sock.recv(4096)
        if not chunk:
            raise RuntimeError("Jasmin CLI closed the connection")
        data += chunk
    return data.decode("utf-8", "replace")


def command(sock, value):
    sock.sendall((value + "\n").encode())
    return read_until(sock, b"jcli : ")


with socket.create_connection((HOST, PORT), timeout=15) as sock:
    read_until(sock, b"Username: ")
    sock.sendall((CLI_USER + "\n").encode())
    read_until(sock, b"Password: ")
    sock.sendall((CLI_PASSWORD + "\n").encode())
    read_until(sock, b"jcli : ")

    groups = command(sock, "group -l")
    if API_UID not in groups:
        command(sock, "group -a")
        command(sock, "gid laravel")
        command(sock, "ok")

    users = command(sock, "user -l")
    if API_UID not in users:
        command(sock, "user -a")
        command(sock, "gid laravel")
        command(sock, f"uid {API_UID}")
        command(sock, f"username {API_USERNAME}")
        command(sock, f"password {API_PASSWORD}")
        command(sock, "mt_messaging_cred authorization http_send True")
        command(sock, "mt_messaging_cred authorization http_balance True")
        command(sock, "mt_messaging_cred authorization http_rate True")
        command(sock, "mt_messaging_cred authorization dlr_level True")
        command(sock, "mt_messaging_cred authorization http_dlr_method True")
        command(sock, "mt_messaging_cred quota balance 100000")
        command(sock, "mt_messaging_cred quota submit_sm_count 100000")
        command(sock, "ok")

print(f"Jasmin default HTTP user ready: {API_USERNAME}")
