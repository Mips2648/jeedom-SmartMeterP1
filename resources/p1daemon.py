from jeedomdaemon.base_daemon import BaseDaemon

from p1host import P1Host


class P1Daemon(BaseDaemon):
    def __init__(self) -> None:
        super().__init__(on_message_cb=self.on_message)

        self._p1: dict[str, P1Host] = {}
        self._counters = {}
        self._last_values = {}

    async def on_message(self, message: list):
        host: str = message['host']
        if message['action'] == "connect":
            port: str = message['port']
            await self.__connect(host, port)
        elif message['action'] == "disconnect":
            await self.__disconnect(host)

    async def __connect(self, host: str, port: int):
        await self.__disconnect(host)
        self._logger.debug("Connecting to p1 at %s:%i", host, port)
        new_host = P1Host(self, host, port)
        self._p1[host] = new_host
        await new_host.start()

    async def __disconnect(self, host: str):
        if host not in self._p1:
            self._logger.debug("Not connected to p1 at %s", host)
            return
        self._logger.debug("Disconnecting from p1 at %s", host)
        await self._p1[host].stop()
        del self._p1[host]


P1Daemon().run()
