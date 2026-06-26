from getpass import getpass
from djitellopy import Tello
import subprocess
import sys


TELLO_IP = "192.168.10.1"


def ask_yes_no(prompt: str) -> bool:
    answer = input(f"{prompt} [y/N]: ").strip().lower()
    return answer in ("y", "yes")


def ping_tello(ip: str) -> bool:
    try:
        result = subprocess.run(
            ["ping", "-c", "2", "-W", "2", ip],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            check=False
        )
        return result.returncode == 0
    except Exception:
        return False


print("Wi-Fi 정보는 2.4GHz 대역에서만 사용할 수 있습니다.")
print("먼저 PC가 Tello 기본 Wi-Fi(TELLO-XXXXXX)에 연결되어 있어야 합니다.")
print()

connected = ask_yes_no("처음 Tello Wi-Fi에 접속했나요?")
if not connected:
    print("먼저 Tello 기본 Wi-Fi에 연결한 뒤 다시 실행해주세요.")
    sys.exit(1)

print(f"Tello 드론({TELLO_IP}) ping 확인 중...")
if not ping_tello(TELLO_IP):
    print("Tello 드론 ping 확인에 실패했습니다.")
    print("PC가 TELLO-XXXXXX Wi-Fi에 연결되어 있는지 확인해주세요.")
    sys.exit(1)

print("Tello 드론 ping 확인 성공")
print()

ssid = input("새 Wi-Fi SSID: ").strip()
password = getpass("새 Wi-Fi 비밀번호: ").strip()

if not ssid:
    print("SSID를 입력해주세요.")
    sys.exit(1)

if not password:
    print("비밀번호를 입력해주세요.")
    sys.exit(1)

tello = Tello()

try:
    tello.connect()

    if tello.get_battery() > 0:
        print ("드론에 성공적으로 연결하였습니다. 남은 베터리는 {}% 입니다.".format(tello.get_battery()))

    print(tello.send_command_with_return(f"ap {ssid} {password}"))

finally:
    try:
        tello.end()
    except Exception:
        pass

print("Wi-Fi 설정이 완료되었습니다.")
print("드론의 IP는 아래의 명령어로 확인할 수 있습니다.")
print("예) sudo nmap -sU -p 8889 172.16.0.0/24")
