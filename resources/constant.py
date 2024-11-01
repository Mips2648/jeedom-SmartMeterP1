CODES_WITH_COUNTER = [
    "1.8.1",  # import high
    "1.8.2",  # import low
    "2.8.1",  # export high
    "2.8.2",  # export low
    "1.7.0",  # import power
    "2.7.0",  # export power
    "32.7.0",  # voltage 1
    "52.7.0",  # voltage 2
    "72.7.0",  # voltage 3
    "31.7.0",  # intensity 1
    "51.7.0",  # intensity 1
    "71.7.0",  # intensity 1
    "21.7.0",  # import power 1
    "41.7.0",  # import power 2
    "61.7.0",  # import power 3
    "22.7.0",  # export power 1
    "42.7.0",  # export power 2
    "62.7.0",  # export power 3
]

CODE_TARIF_INDICATOR = "96.14.0"
CODE_MESSAGE = "96.13.0"

CODES_WITH_GENERIC_DATA = [
    "96.1.1",  # serial number
    "96.1.4",  # id
    CODE_TARIF_INDICATOR, # tariff indicator
    CODE_MESSAGE, # message
]

UNUSED_CODES = [
    "1.0.0",  # datetime; ex:'240118094756W' => 24/01/18 09:47:56
    "1.4.0",  # power last quarter; not used in plugin
    "1.6.0",  # max power / quarter this month
    "17.0.0",  # power limit for client with pre-paid contract; not used in plugin
    "31.4.0",  # current limit
    "96.3.10",  # breaker state?
    "98.1.0"

]

PATTERN_CODE_WITH_COUNTER = "\d\-\d:(\d+\.\d+\.\d+)\((\d+\.\d{1,3})\*([VAkWh]+){1,3}\)"
PATTERN_CODE_WITH_GENERIC_VALUE = "\d\-\d:(\d+\.\d+\.\d+)\((.*)\)"
