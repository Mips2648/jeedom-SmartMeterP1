import asyncio
import re

from jeedomdaemon.base_daemon import BaseDaemon

from constant import CODE_MESSAGE, CODE_TARIF_INDICATOR, CODES_WITH_COUNTER, CODES_WITH_GENERIC_DATA, UNUSED_CODES, PATTERN_CODE_WITH_COUNTER, PATTERN_CODE_WITH_GENERIC_VALUE

class P1Daemon(BaseDaemon):
    def __init__(self) -> None:
        super().__init__(on_message_cb=self.on_messssage)

        self._p1: dict[str, asyncio.Task] = {}
        self._counters = {}

    async def on_messssage(self, message: list):
        host: str = message['host']
        if message['action'] == "connect":
            port: str = message['port']
            await self.__connect(host, port)
        elif message['action'] == "disconnect":
            await self.__disconnect(host)

    async def __connect(self, host: str, port: int):
        await self.__disconnect(host)

        self._logger.debug("Connecting to p1 at %s:%i", host, port)
        reader, writer = await asyncio.open_connection(host, port)
        self._p1[host] = asyncio.create_task(self.__read_p1(host, reader))

    async def __disconnect(self, host: str):
        if host not in self._p1:
            self._logger.debug("Not connected to p1 at %s", host)
            return
        self._logger.debug("Disconnecting from p1 at %s", host)
        self._p1[host].cancel()
        del self._p1[host]

    async def __read_p1(self, host: str, reader: asyncio.StreamReader):
        try:
            self._logger.info("[%s] Connected and start reading values", host)
            await self.send_to_jeedom({host :{"status": 1}})
            self._counters[host] = {}
            while True:
                await asyncio.sleep(0.01)
                data = await reader.readline()
                message = data.decode().strip()
                if message == "":
                    continue
                await self.__decode_line(host, message)

        except asyncio.CancelledError:
            await self.send_to_jeedom({host :{"status": 0}})
            self._logger.info("[%s] Connection closed", host)

    async def __decode_line(self, host: str, line: str):
        try:
            if line.startswith("!"):
                await self.add_change(f"{host}::totalImport", self._counters[host]['1.8.1'] + self._counters[host]['1.8.2'])
                await self.add_change(f"{host}::totalExport", self._counters[host]['2.8.1'] + self._counters[host]['2.8.2'])
                await self.add_change(f"{host}::Import-Export", self._counters[host]['1.7.0'] - self._counters[host]['2.7.0'])
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
                    self._counters[host][code] = value
                    await self.add_change(f"{host}::{code}", value)
                elif code not in UNUSED_CODES:
                    self._logger.warning("[%s] Unknown code %s: %s", host, code, line)

            elif matches := re.search(PATTERN_CODE_WITH_GENERIC_VALUE, line, flags=re.IGNORECASE):
                code = matches.group(1)
                data = matches.group(2)
                if data == "":
                    return

                if code in CODES_WITH_GENERIC_DATA:
                    if code == CODE_TARIF_INDICATOR:
                        value = int(data == "0001")
                    elif code == CODE_MESSAGE:
                        self._logger.info("[%s] Message from P1: %s", host, data)

                    self._counters[host][code] = data
                    await self.add_change(f"{host}::{code}", data)

                elif code not in UNUSED_CODES:
                    self._logger.warning("[%s] Unknown code %s: %s", host, code, line)
            else:
                self._logger.warning("[%s] Unknown line: %s", host, line)
        except Exception as e:
            self._logger.error("[%s] Error decoding line: %s", host, e)

P1Daemon().run()
