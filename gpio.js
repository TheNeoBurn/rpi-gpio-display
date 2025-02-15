var GPIO = {
    URL: 'gpio.php',

    MODE_INPUT: 'INPUT',
    MODE_INPUT_PULLUP: 'INPUT_PULLUP',
    MODE_INPUT_PULLDOWN: 'INPUT_PULLDOWN',
    MODE_OUTPUT: "OUTPUT",
    MODE_NONE: 'NONE',

    _intervalId: null,
    _setups: [],
    _sets: [],
    _lastValues: [],
    _isWorking: false,
    _onUpdate: function(pins) { },
    _onChange: function(pin, value, mode, pull) { },

    _send: function(url, onResult, onError) {
        fetch(url)
            .then(response => {
                if (response.ok) {
                    return response.json();
                } else {
                    throw 'HTTP error: ' + response.status;
                }
            })
            .then(function(json) {
                if (typeof(onResult) == 'function') {
                    onResult(json);
                }
            })
            .catch(function(error) {
                if (typeof(onError) == 'function') {
                    onError(null, error);
                }
            });
    },

    _execSetup: function(item) {
        GPIO._isWorking = true;

        GPIO._send(GPIO.URL + '?cmd=mode&pin='+item.pin+'&mode='+item.mode, 
            json => {
                GPIO._isWorking = false;
            }, 
            error => {
                item.counter++;
                if (item.counter < 5) {
                    // Re-enqueue the item
                    GPIO._setups.push(item);
                } else {
                    // Give up
                    console.error('Unable to setup pin ' + item.pin + ' to ' + item.mode + '!');
                }
                GPIO._isWorking = false;
            }
        );
    },

    _execWrite: function(item) {
        GPIO._isWorking = true;

        GPIO._send(GPIO.URL + '?cmd=set&pin='+item.pin+'&value='+item.value, 
            json => {
                GPIO._isWorking = false;
            }, 
            error => {
                console.error('Unable to write pin ' + item.pin + ' to ' + item.value + '!');
                GPIO._isWorking = false;
            }
        );
    },

    _execRead: function() {
        GPIO._isWorking = true;

        GPIO._send(GPIO.URL + '?cmd=get', 
            json => {
                for (var i = 0; i < json.length; i++) {
                    var item = json[i];

                    if (typeof(item.pin) == 'undefined') { console.log("pin missing: " + item); continue; }
                    if (typeof(item.mode) == 'undefined') { console.log("mode missing: " + item); continue; }
                    if (typeof(item.pull) == 'undefined') { console.log("pull missing: " + item); continue; }
                    if (typeof(item.value) == 'undefined') { console.log("value missing: " + item); continue; }
                    if (typeof(item.description) == 'undefined') { console.log("description missing: " + item); continue; }

                    var pin = item.pin;
                    var mode = item.mode;
                    var pull = item.pull;
                    var value = item.value;
                    var desc = item.description;
                    var isInput = mode.startsWith('INPUT');

                    var old = null;
                    var changed = false;
                    for (var o = 0; o < GPIO._lastValues.length; o++) {
                        if (GPIO._lastValues[o].pin == pin) {
                            old = GPIO._lastValues[o];
                            break;
                        }
                    }

                    if (old == null) {
                        GPIO._lastValues.push(item);
                        changed = true;
                    } else {
                        if (old.value != value || old.mode != mode || old.pull != pull) {
                            old.value = value;
                            old.mode = mode;
                            old.pull = pull
                            changed = true;
                        }
                        if (old.description != desc) {
                            old.description = desc;
                        }
                    }

                    if (changed && isInput) {
                        try {
                            GPIO._onChange(pin, value, mode, pull);
                        } catch (ex) {
                            console.error(ex.message);
                        }
                    }

                    try {
                        GPIO._onUpdate(json);
                    } catch (ex) {
                        console.error(ex.message);
                    }
                }

                GPIO._isWorking = false;
            }, 
            error => {
                console.error('Unable to read pins!');
                GPIO._isWorking = false;
            }
        );
    },

    _loop: function() {
        if (GPIO._isWorking) return;

        if (GPIO._setups.length > 0) {
            var item = GPIO._setups.splice(0, 1)[0];
            GPIO._execSetup(item);
            return;
        }

        if (GPIO._sets.length > 0) {
            var item = GPIO._sets.splice(0, 1)[0];
            GPIO._execWrite(item);
            return;
        }

        GPIO._execRead();
    },

    setMode: function(pin, mode) {
        if (typeof(pin) != 'number') {
            throw 'pin must be a number!';
        }
        if (mode != GPIO.MODE_INPUT && mode != GPIO.MODE_INPUT_PULLUP && mode != GPIO.MODE_INPUT_PULLDOWN && mode != GPIO.MODE_OUTPUT && mode != GPIO.MODE_NONE) {
            throw 'mode must be GPIO.MODE_*!';
        }

        GPIO._setups.push({ pin: pin, mode: mode, counter: 0 });
    },

    setValue: function(pin, value)  {
        if (typeof(pin) != 'number') {
            throw 'pin must be a number!';
        }
        if (value !== true && value !== false && value !== 1 && value !== 0) {
            throw 'value must be a boolean or number!';
        }

        GPIO._sets.push({ pin: pin, value: value === true || value === 1});
    },

    init: function(setModes, onChangeHandler, onUpdateHandler) {
        if (setModes) {
            for (var i = 0; i < setModes.length; i++) {
                if (typeof(setModes[i].pin) == 'number' && typeof(setModes[i].mode) == 'string') {
                    GPIO._setups.push({ pin: setModes[i].pin, mode: setModes[i].mode, counter: 0 });
                } else {
                    throw 'Wrong setMode item! (' + i + ')';
                }
            }
        }

        if (typeof(onChangeHandler) == 'function') {
            GPIO._onChange = onChangeHandler;
        }

        if (typeof(onUpdateHandler) == 'function') {
            GPIO._onUpdate = onUpdateHandler;
        }

        GPIO._intervalId = window.setInterval(GPIO._loop, 100);
    }
}