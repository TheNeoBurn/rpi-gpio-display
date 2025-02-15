<?php
$REX_PINCTRL_GET = '/^\s*(?<pin>[0-9]{1,2}):\s*(?<mode>ip|op|a[0-9])\s*(--\s*)?(?<pull>p[dhlnu])\s*\|\s*(?<value>hi|lo)\s+\/\/\s*(?<desc>[^\r\n]+)$/i';

$cmd = $_GET['cmd'];

switch ($cmd) {
    case 'get':
        $result = array();
        $output = explode("\n", shell_exec("pinctrl get"));
        foreach ($output as $key => $line) {
            if (preg_match($REX_PINCTRL_GET, $line, $matches)) {
                $mode = $matches['mode'];
                $pull = $matches['pull'];
                switch ($mode) {
                    case 'ip':
                        switch ($pull) {
                            case 'pu': $mode = 'INPUT_PULLUP'; break;
                            case 'pd': $mode = 'INPUT_PULLDOWN'; break;
                            default: $mode = 'INPUT';
                        }
                        break;
                    
                    case 'op':
                        $mode = 'OUTPUT';
                        break;

                    default:
                        $mode = strtoupper($mode);
                        break;
                }
                switch ($pull) {
                    case 'pd': $pull = 'DOWN'; break;
                    case 'pu': $pull = 'UP'; break;
                    case 'pn': $pull = ''; break;
                }

                $result[] = array(
                    'pin' => intval($matches['pin']),
                    'mode' => $mode,
                    'pull' => $pull,
                    'value' => $matches['value'] == 'hi',
                    'description' => $matches['desc'],
                );
            }
        }
        header('Content-Type: application/json; charset=utf-8');
        echo(json_encode($result));
        break;

    case 'set':
        $pin = $_GET['pin'] ?? '';
        if (preg_match("/^[0-9]{1,2}$/", $pin) != 1) {
            die("Invalid pin name $pin!");
        }
        $value = strtolower($_GET['value'] ?? die('Value missing!'));
        $value = (($value == 'true') || ($value == '1') || ($value == 'hi') || ($value == 'high') || ($value == 'dh')) ? 'dh' : 'dl';
        $line = shell_exec("pinctrl set $pin op pn $value");
        $result = array('result' => $line);
        header('Content-Type: application/json; charset=utf-8');
        echo(json_encode($result));
        break;

    case 'mode':
        $pin = $_GET['pin'] ?? die('Pin missing!');
        if (preg_match("/^[0-9]{1,2}$/", $pin) != 1) {
            die("Invalid pin name $pin!");
        }

        $mode = strtolower($_GET['mode'] ?? die('Mode missing!'));
        $opts = '';
        switch ($mode) {
            case 'input':
                $opts = 'ip pn';
                break;
            case 'input_pullup':
                $opts = 'ip pu';
                break;
            case 'input_pulldown':
                $opts = 'ip pn';
                break;
            case 'output':
                $opts = 'op pn';
                break;
            case 'output_high':
                $opts = 'op pn dh';
                break;
            case 'output_low':
                $opts = 'op pn dl';
                break;
            case 'none':
                $opts = 'no';
                break;
            default:
                die("Unsupported mode: $mode");
        }

        $line = shell_exec("pinctrl set $pin $opts");

        $result = array('result' => $line);
        header('Content-Type: application/json; charset=utf-8');
        echo(json_encode($result));
        break;

    default:
        die("Unsupported command: $cmd");
}

?>
