"""
Tello 카메라 스트림만 표시합니다 (이륙·조작 없음).

사용 전: PC를 Tello Wi-Fi(Tello-XXXX)에 연결하세요.
종료: 창이 포커스일 때 q 또는 Esc.

Ubuntu에서 창이 안 뜨면 DISPLAY가 필요합니다 (로컬 데스크톱 또는 `echo $DISPLAY`).
"""

from __future__ import annotations

import sys

import cv2

from modules.ctrl import controller


WINDOW = "Tello stream"


def main() -> int:
    try:
        controller.connect("192.168.0.21")
        controller.stream_on()

        while True:
            frame = controller.get_frame_bgr()
            if frame is not None:
                cv2.imshow(WINDOW, frame)

            key = cv2.waitKey(1) & 0xFF
            if key in (ord("q"), 27):  # q or Esc
                break
    except KeyboardInterrupt:
        pass
    finally:
        cv2.destroyAllWindows()
        try:
            controller.stream_off()
        except Exception:
            pass
        try:
            controller.disconnect()
        except Exception:
            pass

    return 0


if __name__ == "__main__":
    sys.exit(main())
