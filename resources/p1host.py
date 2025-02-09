from __future__ import annotations

import logging
import asyncio
import re
import time

from jeedomdaemon.base_daemon import BaseDaemon

from constant import CODE_MESSAGE, CODE_TARIF_INDICATOR, CODES_WITH_COUNTER, CODES_WITH_GENERIC_DATA, UNUSED_CODES, PATTERN_CODE_WITH_COUNTER, PATTERN_CODE_WITH_GENERIC_VALUE


class P1Host:
    def __init__(self, daemon: BaseDaemon, host: str, port: int):
        self.__host = host
        self.__port = port
        self.__daemon = daemon
        self.__last_read_time = time.time()
        self.__heartbeat_timeout = 10
        self.__counters = {}
        self._logger = logging.getLogger(__name__)

    @property
    def read_task(self):
        return self.__read_task

    @property
    def last_read_time(self):
        return self.__last_read_time

    @property
    def counters(self):
        return self.__counters

    @counters.setter
    def counters(self, value):
        self.__counters = value

    async def start(self):
        await self.__start_read()
        await asyncio.sleep(0.1)
        self.__heartbeat_task = asyncio.create_task(self.__heartbeat())

    async def stop(self):
        try:
            self.__heartbeat_task.cancel()
            self.__read_task.cancel()
        except Exception:
            pass
        self.__read_task = None
        self.__counters = {}
        self.__last_read_time = 0

    async def __start_read(self):
        reader, writer = await asyncio.open_connection(self.__host, self.__port)
        self.__read_task = asyncio.create_task(self.__read_p1(reader))

    async def _reset(self):
        try:
            self.__read_task.cancel()
        except Exception:
            pass
        await self.__start_read()

    async def __heartbeat(self):
        self.__last_read_time = time.time()
        while True:
            await asyncio.sleep(self.__heartbeat_timeout)
            if time.time() - self.__last_read_time > self.__heartbeat_timeout:
                self._logger.warning("[%s] No values received for %i seconds, reset connection", self.__host, self.__heartbeat_timeout)
                await self._reset()
            else:
                self._logger.info("[%s] Heartbeat ok", self.__host)

    async def __read_p1(self, reader: asyncio.StreamReader):
        try:
            self._logger.info("[%s] Connected and start reading values", self.__host)
            await self.__daemon.send_to_jeedom({self.__host: {"status": 1}})
            self.__counters = {}
            self.__last_read_time = time.time()
            while True:
                await asyncio.sleep(0.01)
                data = await asyncio.wait_for(reader.readline(), self.__heartbeat_timeout)
                message = data.decode().strip()
                if message == "":
                    continue
                self.__last_read_time = time.time()
                await self.__decode_line(message)

        except asyncio.TimeoutError:
            self._logger.warning("[%s] Timeout reading values", self.__host)
        except asyncio.CancelledError:
            self._logger.info("[%s] Connection closed", self.__host)
        except Exception as e:
            self._logger.error("[%s] Error reading values: %s", self.__host, e)
        finally:
            await self.__daemon.send_to_jeedom({self.__host: {"status": 0}})

    async def __decode_line(self, line: str):
        try:
            self._logger.debug("[%s] Decoding line: %s", self.__host, line)
            if line.startswith("!"):
                await self.__daemon.add_change(f"{self.__host}::totalImport", self.__counters['1.8.1'] + self.__counters['1.8.2'])
                await self.__daemon.add_change(f"{self.__host}::totalExport", self.__counters['2.8.1'] + self.__counters['2.8.2'])
                await self.__daemon.add_change(f"{self.__host}::Import-Export", self.__counters['1.7.0'] - self.__counters['2.7.0'])
                return
            elif line.startswith("/"):
                return

            if matches := re.search(PATTERN_CODE_WITH_COUNTER, line, flags=re.IGNORECASE):
                code = matches.group(1)
                if code in CODES_WITH_COUNTER:
                    value = float(matches.group(2))
                    unit = matches.group(3)
                    if unit == "kW":
                        value *= 1000
                    self.__counters[code] = value
                    await self.__daemon.add_change(f"{self.__host}::{code}", value)
                elif code not in UNUSED_CODES:
                    self._logger.warning("[%s] Unknown counter code %s: %s", self.__host, code, line)

            elif matches := re.search(PATTERN_CODE_WITH_GENERIC_VALUE, line, flags=re.IGNORECASE):
                code = matches.group(1)
                data = matches.group(2)
                if data == "":
                    return

                if code in CODES_WITH_GENERIC_DATA:
                    if code == CODE_TARIF_INDICATOR:
                        data = int(data == "0001")
                    elif code == CODE_MESSAGE:
                        self._logger.info("[%s] Message from P1: %s", self.__host, data)

                    self.__counters[code] = data
                    await self.__daemon.add_change(f"{self.__host}::{code}", data)

                elif code not in UNUSED_CODES:
                    self._logger.warning("[%s] Unknown data code %s: %s", self.__host, code, line)
            else:
                self._logger.warning("[%s] Unknown line: %s", self.__host, line)
        except Exception as e:
            self._logger.error("[%s] Error decoding line: %s", self.__host, e)
