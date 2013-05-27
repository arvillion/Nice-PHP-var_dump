<?php
/**
 * @author Egor Spivac. golden13@gmail.com
 *
 * Feel free to use code
 *
 */

/**
 * Simplifying method. Modified var_dump. Very nice and smart :)
 *
 * @param mix $value variables
 */
function vd()
{
    $args = func_get_args();
    $i = 0;
    foreach ($args AS $var) {
        NDUMPER::vd($var, $i++);
    }
}


// Configuration
$NDUMPER_LIB_CONSTRUCTOR = array(
    'config' => array(
        'expanded' => true, // Show all levels expanded
        'recursion.limit' => 7, // Limit of recursion levels (For Symfony2 use less than 15 :) )
        'items.limit' => 100, // Limit for items. Not works now. Will be added in next commit
    ),

    // Variable = {{varname}} will be replaced by values
    'templates' => array(
        'global.styles' => '<style>
                            .dbgBlock .s {color:#008000;}
                            .dbgBlock .i {color:#0000FF;}
                            .dbgBlock .a {color:#b94a48;}
                            .dbgBlock .o {color:#f89406;}
                            .dbgBlock .k {color:#A94F4F;}
                            .dbgBlock .ok {color:#000000;}
                            .dbgBlock .em {font-style:italic;color:#CCCCCC;}
                            .dbgBlock .n {font-style:italic;color:#CCCCCC;}
                            .dbgBlock {border:solid 1px #CCCCCC!important;padding:10px!important;font-size:9pt!important;background-color:#FFFFFF!important;color:#000000!important;font-weight:normal;}
                            .dbgBlock PRE {border:none;background-color:inherit;white-space:pre;font-weight:normal;}
                            .dbgBlock .m{display:block;width:auto;background-color:#585858;color:#dcf356;padding:5px;font-size:10pt;height:20px;font-weight:bold;font-family:Arial;}
                            .dbgBlock A {display:inline;background-color:#FFFFFF;color:blue;}
                            .dbgBlock DIV {margin-left:20px;display:{{hideShow}}}
                            .dbgBlock P {font-size:8pt;color:#222222;}
                            .dbgBlock DIV {border:solid 2px #FFFFFF;}
                            .dbgBlock DIV:hover {border:solid 2px #F5F5F5;}
                           </style>',

        'global.js' => '<script language="JavaScript">
                            function dshdbg(id, id2)
                            {
                                var el = document.getElementById(id);
                                var el2 = document.getElementById(id2);
                                if (el.style.display == "") {
                                    el.style.display = "{{hideShow}}";
                                }
                                if (el.style.display == "block" && el.style.display != "") {
                                    el.style.display = "none";
                                    if (typeof el2 != "undefined" && el2 != null) el2.innerHTML = "[+]";
                                } else {
                                    el.style.display = "block";
                                    if (typeof el2 != "undefined" && el2 != null) el2.innerHTML = "[-]";
                                }
                                return false;
                            }
                        </script>',


        'global.block' => '<div class="dbgBlock">
                            <a class="m" href="#" onclick="JavaScript:dshdbg(\'{{blockId}}\', \'{{linkId}}\');">{{name}} ({{type}}) {{size}}
                            <span id="{{linkId}}">[{{char}}]</span></a>
                            <div id="{{blockId}}">
                            <pre>{{content}}<br><span class="t">{{trace}}</span></pre></div>
                            </div>',

        'boolean.value' => '<span class="b">{{value}}</span>',
        'double.value'  => '<span class="d">{{value}}</span>',
        'integer.value' => '<span class="i">({{type}}) </span><span class="i">{{value}}</span>',
        'resource.value' => '<span class="r"}>{{value}}</span>',

        'null.value'    => '<span class="n">{{value}}</span>',
        'empty.value'   => '<span class="em">empty</span>',

    )
);

/**
 * Dumper class
 */
class NDUMPER
{
    // HTML Templates
    private static $_templates = array();

    // config
    private static $_config = array();

    private static $_localCounter = 0;

    /**
     * Nice var_dump.
     *
     * @global $NVDUMPER_TEMPLATES
     * @param mix $value variable
     */
    public static function vd($value, $position)
    {
        global $NDUMPER_LIB_CONSTRUCTOR;

        self::$_templates = $NDUMPER_LIB_CONSTRUCTOR['templates'];
        self::$_config    = $NDUMPER_LIB_CONSTRUCTOR['config'];

        // replace expanded collapsed default state
        $replaceExpanded = array(
            '{{hideShow}}' => ((self::$_config['expanded'])? 'block' : 'none'),
        );
        self::$_templates['global.js'] = strtr(self::$_templates['global.js'], $replaceExpanded);
        self::$_templates['global.styles'] = strtr(self::$_templates['global.styles'], $replaceExpanded);

        $result = '';

        self::$_localCounter++;

        if (self::$_localCounter == 1) {
            $result .= self::$_templates['global.styles'];
            $result .= self::$_templates['global.js'];
        }

        $traceArray = self::_getTraceAndName($position);
        $trace = $traceArray['trace'];
        $name  = $traceArray['name'];
        $ids   = self::_getIds(true);
        $type  = gettype($value);

        $result2 = self::$_templates['global.block'];
        $result2 = strtr($result2, array(
            '{{blockId}}' => $ids['block'],
            '{{linkId}}'  => $ids['link'],
            '{{name}}'    => $name,
            '{{type}}'    => $type,
            '{{size}}'    => count($value),
            '{{content}}' => self::_getVarContent($value, 0),
            '{{trace}}'   => $trace,
            '{{char}}'    => (self::$_config['expanded']? '-' : '+'),
        ));

        $result .= $result2;

        echo $result;
    }

    /**
     * Determine json data
     *
     * @param $string
     *
     * @return array('result'=>bool, 'data'=>array)
     */
    private static function _getJson($string)
    {
        //$isJson = !preg_match('/[^,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t]/',
        //    preg_replace('/"(\.|[^"\])*"/g', '', $string));
        //TODO: modify for better performance
        $data = json_decode($string);
        $isJson = false;
        if (json_last_error() === JSON_ERROR_NONE && $data!=null && $data != $string) {
            $isJson = true;
        }
        return array(
            'result' => $isJson,
            'data'   => $data
        );

        /*
        if (function_exists('json_last_error') && function_exists('json_decode')) {
            if (trim($string)==='') {
                $data = '';
            } else {
                $data = json_decode($string);
            }
            echo $string;
            return array(
                'result' => (json_last_error() == JSON_ERROR_NONE),
                'data'   => $data,
            );
        } else {
            return array(
                'result' => false,
                'data'   => array(),
            );
        }*/
    }

    /**
     * Trying determine variable name
     * @param $traceArray
     * @param $position
     *
     * @return string
     */
    private static function getVarName($traceArray, $position)
    {
        $result = '';
        if (isset($traceArray[2])) {
            $filename = $traceArray[2]['file'];
            $lineNumber = $traceArray[2]['line'] - 1;
            $fileContent = @file($filename);
            if (empty($fileContent)) {
                return $result;
            }

            if (isset($fileContent[$lineNumber])) {
                $line = $fileContent[$lineNumber];
                preg_match("/vd[\s]{0,}\((.{0,})\)/i", $line, $matches);
                if (isset($matches[1])) {
                    $arr = explode(',', $matches[1]);
                    if (count($arr) > 1 && isset($arr[$position])) {
                        $result = trim($arr[$position]);
                    } else {
                        $result = $matches[1];
                    }
                }
            }
        }
        if (strlen($result) > 200) {
            $result = substr($result, 0, 200);
        }
        return $result;
    }

    /**
     * Build id's for block and '+' label
     * @param bool $increment
     *
     * @return array
     */
    protected static function _getIds($increment = false)
    {
        if ($increment) {
            self::$_localCounter++;
        }
        $blockId = 'dbgb' . self::$_localCounter;
        $linkId  = 'dbgl' . self::$_localCounter;
        return array(
            'block' => $blockId,
            'link'  => $linkId
        );
    }

    /**
     * Get back trace
     * @param int $position
     * @return string
     */
    private static function _getTraceAndName($position)
    {
        $trace = debug_backtrace();
        $name = self::getVarName($trace, $position);

        $trace_text = '';
        if (!empty($trace)) {
            $trace = array_reverse($trace);
            $n = 1;
            foreach ($trace as $v) {
                if ($v['function'] == 'vd' && empty($v['class'])) {
                    $bs = '<b>';
                    $be = '</b>';
                } else {
                    $bs = $be = '';
                }
                $trace_text .=
                    ($n++) . '. ' . $bs .
                        ((isset($v['class']))? 'class ' . $v['class'] . '->' : '') . $v['function'] . " in " .
                        ((isset($v['file']))? $v['file'] . ' (' . $v['line'] . ')' : '') . $be . "\n";
            }
            $trace_text = "<b>Trace:</b>\n" . $trace_text;
        }
        return array('name' => $name, 'trace' => $trace_text);
    }


    /**
     * Build one var block.
     * @param array $arr
     * @param int   $level
     *
     * @return string
     */
    private static function _getVarContent($arr, $level = 0)
    {
        $str = '';
        $level2 = $level + 1;

        // limit of recursion
        if ($level > self::$_config['recursion.limit']) {
            $str .= '...(too deep)...';
            return $str;
        }

        $varType = gettype($arr);

        switch ($varType) {
            case 'boolean':
                if ($arr === true) {
                    $value = 'true';
                } else {
                    $value = 'false';
                }
                $str2 = strtr(self::$_templates['boolean.value'], array('{{value}}' => $value));
                $str .= $str2;
                break;
                
            case 'double':
                $str2 = strtr(self::$_templates['double.value'], array('{{value}}' => $arr));
                $str .= $str2;
                break;

            case 'string':
                $len = strlen($arr);
                $jsonResult = self::_getJson($arr);

                // Determine json data
                $isJson = false;
                if ($jsonResult['result'] === true) {
                    $isJson = true;
                    $varType .= ', json';
                }
                $jsonArray = $jsonResult['data'];
                $str .= "<span>({$varType}) [{$len}] </span>";
                if ($isJson) {
                    $count = count($jsonArray);
                    //echo $count;
                    if ($count === 0) {
                        $str .= self::$_templates['empty.value'];
                        break;
                    }
                    $str .= '<span class="s">'.$arr.'</span>';
                    $ids = self::_getIds(true);
                    $str .= "<a href=\"#\" onClick=\"return dshdbg('{$ids['block']}', '{$ids['link']}');\"> json data: <span id=\"{$ids['link']}\">[".((self::$_config['expanded'])? '-' : '+') . "]</span></a>\n";
                    $str .= "<div id=\"{$ids['block']}\">";
                    foreach ($jsonArray as $key => $value) {
                        $str .= '<span class="k">'.$key.'</span>';
                        $str .= ' => ';
                        $str .= self::_getVarContent($value, $level2) . "\n";
                    }
                    $str .= "</div>";
                    $str .= "}";
                } else {
                    if ($len > 155) {
                        $title = '';
                        $value = $arr;
                        $ids = self::_getIds(true);
                        $str .= "{ <a href=\"#\" onClick=\"return dshdbg('{$ids['block']}', '{$ids['link']}');\">{$title}<span id=\"{$ids['link']}\">[".((self::$_config['expanded'])? '-' : '+') . "]</span></a>\n";
                        $str .= "<div class=\"s\" id=\"{$ids['block']}\">" . $value . '</div>';
                        $str .= "}\n";
                    } else {
                        $value = str_replace("\n", '\n', $arr);
                        $str .= "<span class=\"s\">{$value}</span>";
                    }
                }
                break;

            case 'integer':
                $str2 = strtr(self::$_templates['integer.value'], array('{{type}}' => $varType, '{{value}}' => $arr));
                $str .= $str2;
                //$str .= "<span>({$varType}) </span>";
                //$str .= "<span class=\"i\">{$arr}</span>";
                break;

            case 'resource':
                $str2 = strtr(self::$_templates['resource.value'], array('{{value}}' => $arr));
                $str .= $str2;
                break;

            case 'NULL':
                $str2 = strtr(self::$_templates['null.value'], array('{{value}}' => $arr));
                $str .= $str2;
                break;

            case 'array':
                $count = count($arr);
                $str .= "<span class=\"a\">{$varType} [{$count}]</span> ";
                if ($count === 0) {
                    $str .= self::$_templates['empty.value'];
                    break;
                }
                $str .= "{ ";
                $ids = self::_getIds(true);
                $str .= "<a href=\"#\" onClick=\"return dshdbg('{$ids['block']}', '{$ids['link']}');\"><span id=\"{$ids['link']}\">[".((self::$_config['expanded'])? '-' : '+') . "]</span></a>\n";
                $str .= "<div id=\"{$ids['block']}\">";
                foreach ($arr as $key => $value) {
                    $str .= '<span class="k">'.$key.'</span>';
                    $str .= ' => ';
                    $str .= self::_getVarContent($value, $level2) . "\n";
                }
                $str .= "</div>";
                $str .= "}";
                break;

            case 'object':
                $objectVars = (array)$arr;
                //print_r($objectVars);
                $count = count($objectVars);
                //echo $count; break;;
                $str .= "<span class=\"o\">{$varType} [{$count}]</span> ";
                if ($count === 0) {
                    $str .= self::$_templates['empty.value'];
                    break;
                }
                $str .= "{ ";
                $ids = self::_getIds(true);
                $str .= "<a href=\"#\" onClick=\"return dshdbg('{$ids['block']}', '{$ids['link']}');\"><span id=\"{$ids['link']}\">[".((self::$_config['expanded'])? '-' : '+') . "]</span></a>\n";
                $str .= "<div id=\"{$ids['block']}\">";
                foreach ($objectVars as $key => $value) {
                    $str .= '<span class="ok">'.$key.'</span>';
                    $str .= ' => ';
                    $str .= self::_getVarContent($value, $level2) . "\n";
                }
                $str .= "</div>";
                $str .= "}\n";

                break;

            default: // "unknown type"
                $str .= "<span>({$varType})</span> ";
                $str .= "<span class=\"u\">{$arr}</span>";
                break;
        }
        return $str;
    }
}

// This is the end, my beautiful friend
