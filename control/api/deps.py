"""API 공통 의존성."""

from __future__ import annotations

from typing import Annotated

from fastapi import Header, HTTPException, Query

from modules.ctrl import is_api_authorized


async def verify_authorization(
    authorization: Annotated[str | None, Header(alias="Authorization")] = None,
) -> None:
    if not is_api_authorized(authorization):
        raise HTTPException(status_code=401, detail="Unauthorized")


async def verify_authorization_flexible(
    authorization: Annotated[str | None, Header(alias="Authorization")] = None,
    token: Annotated[str | None, Query()] = None,
) -> None:
    """헤더 또는 쿼리스트링(`?token=`) 인증을 모두 허용한다.

    `<img src=...>` / `<video>` 처럼 Authorization 헤더를 보낼 수 없는
    브라우저 요청(MJPEG 스트림, 스냅샷)에서 사용한다.
    """
    if is_api_authorized(authorization):
        return
    if is_api_authorized(token):
        return
    raise HTTPException(status_code=401, detail="Unauthorized")
