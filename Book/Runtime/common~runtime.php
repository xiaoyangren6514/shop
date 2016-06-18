<?php namespace {
    function C($name = null, $value = null, $default = null)
    {
        static $_config = array();
        if (empty($name)) {
            return $_config;
        }
        if (is_string($name)) {
            if (!strpos($name, '.')) {
                $name = strtoupper($name);
                if (is_null($value)) return isset($_config[$name]) ? $_config[$name] : $default;
                $_config[$name] = $value;
                return null;
            }
            $name = explode('.', $name);
            $name[0] = strtoupper($name[0]);
            if (is_null($value)) return isset($_config[$name[0]][$name[1]]) ? $_config[$name[0]][$name[1]] : $default;
            $_config[$name[0]][$name[1]] = $value;
            return null;
        }
        if (is_array($name)) {
            $_config = array_merge($_config, array_change_key_case($name, CASE_UPPER));
            return null;
        }
        return null;
    }

    function load_config($file, $parse = CONF_PARSE)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        switch ($ext) {
            case 'php':
                return include $file;
            case 'ini':
                return parse_ini_file($file);
            case 'yaml':
                return yaml_parse_file($file);
            case 'xml':
                return (array)simplexml_load_file($file);
            case 'json':
                return json_decode(file_get_contents($file), true);
            default:
                if (function_exists($parse)) {
                    return $parse($file);
                } else {
                    E(L('_NOT_SUPPORT_') . ':' . $ext);
                }
        }
    }

    if (!function_exists('yaml_parse_file')) {
        function yaml_parse_file($file)
        {
            vendor('spyc.Spyc');
            return Spyc::YAMLLoad($file);
        }
    }
    function E($msg, $code = 0)
    {
        throw new Think\Exception($msg, $code);
    }

    function G($start, $end = '', $dec = 4)
    {
        static $_info = array();
        static $_mem = array();
        if (is_float($end)) {
            $_info[$start] = $end;
        } elseif (!empty($end)) {
            if (!isset($_info[$end])) $_info[$end] = microtime(TRUE);
            if (MEMORY_LIMIT_ON && $dec == 'm') {
                if (!isset($_mem[$end])) $_mem[$end] = memory_get_usage();
                return number_format(($_mem[$end] - $_mem[$start]) / 1024);
            } else {
                return number_format(($_info[$end] - $_info[$start]), $dec);
            }
        } else {
            $_info[$start] = microtime(TRUE);
            if (MEMORY_LIMIT_ON) $_mem[$start] = memory_get_usage();
        }
        return null;
    }

    function L($name = null, $value = null)
    {
        static $_lang = array();
        if (empty($name)) return $_lang;
        if (is_string($name)) {
            $name = strtoupper($name);
            if (is_null($value)) {
                return isset($_lang[$name]) ? $_lang[$name] : $name;
            } elseif (is_array($value)) {
                $replace = array_keys($value);
                foreach ($replace as &$v) {
                    $v = '{$' . $v . '}';
                }
                return str_replace($replace, $value, isset($_lang[$name]) ? $_lang[$name] : $name);
            }
            $_lang[$name] = $value;
            return null;
        }
        if (is_array($name)) $_lang = array_merge($_lang, array_change_key_case($name, CASE_UPPER));
        return null;
    }

    function trace($value = '[think]', $label = '', $level = 'DEBUG', $record = false)
    {
        return Think\Think::trace($value, $label, $level, $record);
    }

    function compile($filename)
    {
        $content = php_strip_whitespace($filename);
        $content = trim(substr($content, 5));
        $content = preg_replace('/\/\/\[RUNTIME\](.*?)\/\/\[\/RUNTIME\]/s', '', $content);
        if (0 === strpos($content, 'namespace')) {
            $content = preg_replace('/namespace\s(.*?);/', 'namespace \\1{', $content, 1);
        } else {
            $content = 'namespace {' . $content;
        }
        if ('?>' == substr($content, -2)) $content = substr($content, 0, -2);
        return $content . '}';
    }

    function T($template = '', $layer = '')
    {
        if (false === strpos($template, '://')) {
            $template = 'http://' . str_replace(':', '/', $template);
        }
        $info = parse_url($template);
        $file = $info['host'] . (isset($info['path']) ? $info['path'] : '');
        $module = isset($info['user']) ? $info['user'] . '/' : MODULE_NAME . '/';
        $extend = $info['scheme'];
        $layer = $layer ? $layer : C('DEFAULT_V_LAYER');
        $auto = C('AUTOLOAD_NAMESPACE');
        if ($auto && isset($auto[$extend])) {
            $baseUrl = $auto[$extend] . $module . $layer . '/';
        } elseif (C('VIEW_PATH')) {
            $baseUrl = C('VIEW_PATH');
        } elseif (defined('TMPL_PATH')) {
            $baseUrl = TMPL_PATH . $module;
        } else {
            $baseUrl = APP_PATH . $module . $layer . '/';
        }
        $theme = substr_count($file, '/') < 2 ? C('DEFAULT_THEME') : '';
        $depr = C('TMPL_FILE_DEPR');
        if ('' == $file) {
            $file = CONTROLLER_NAME . $depr . ACTION_NAME;
        } elseif (false === strpos($file, '/')) {
            $file = CONTROLLER_NAME . $depr . $file;
        } elseif ('/' != $depr) {
            $file = substr_count($file, '/') > 1 ? substr_replace($file, $depr, strrpos($file, '/'), 1) : str_replace('/', $depr, $file);
        }
        return $baseUrl . ($theme ? $theme . '/' : '') . $file . C('TMPL_TEMPLATE_SUFFIX');
    }

    function I($name, $default = '', $filter = null, $datas = null)
    {
        static $_PUT = null;
        if (strpos($name, '/')) {
            list($name, $type) = explode('/', $name, 2);
        } elseif (C('VAR_AUTO_STRING')) {
            $type = 's';
        }
        if (strpos($name, '.')) {
            list($method, $name) = explode('.', $name, 2);
        } else {
            $method = 'param';
        }
        switch (strtolower($method)) {
            case 'get' :
                $input =& $_GET;
                break;
            case 'post' :
                $input =& $_POST;
                break;
            case 'put' :
                if (is_null($_PUT)) {
                    parse_str(file_get_contents('php://input'), $_PUT);
                }
                $input = $_PUT;
                break;
            case 'param' :
                switch ($_SERVER['REQUEST_METHOD']) {
                    case 'POST':
                        $input = $_POST;
                        break;
                    case 'PUT':
                        if (is_null($_PUT)) {
                            parse_str(file_get_contents('php://input'), $_PUT);
                        }
                        $input = $_PUT;
                        break;
                    default:
                        $input = $_GET;
                }
                break;
            case 'path' :
                $input = array();
                if (!empty($_SERVER['PATH_INFO'])) {
                    $depr = C('URL_PATHINFO_DEPR');
                    $input = explode($depr, trim($_SERVER['PATH_INFO'], $depr));
                }
                break;
            case 'request' :
                $input =& $_REQUEST;
                break;
            case 'session' :
                $input =& $_SESSION;
                break;
            case 'cookie' :
                $input =& $_COOKIE;
                break;
            case 'server' :
                $input =& $_SERVER;
                break;
            case 'globals' :
                $input =& $GLOBALS;
                break;
            case 'data' :
                $input =& $datas;
                break;
            default:
                return null;
        }
        if ('' == $name) {
            $data = $input;
            $filters = isset($filter) ? $filter : C('DEFAULT_FILTER');
            if ($filters) {
                if (is_string($filters)) {
                    $filters = explode(',', $filters);
                }
                foreach ($filters as $filter) {
                    $data = array_map_recursive($filter, $data);
                }
            }
        } elseif (isset($input[$name])) {
            $data = $input[$name];
            $filters = isset($filter) ? $filter : C('DEFAULT_FILTER');
            if ($filters) {
                if (is_string($filters)) {
                    if (0 === strpos($filters, '/')) {
                        if (1 !== preg_match($filters, (string)$data)) {
                            return isset($default) ? $default : null;
                        }
                    } else {
                        $filters = explode(',', $filters);
                    }
                } elseif (is_int($filters)) {
                    $filters = array($filters);
                }
                if (is_array($filters)) {
                    foreach ($filters as $filter) {
                        if (function_exists($filter)) {
                            $data = is_array($data) ? array_map_recursive($filter, $data) : $filter($data);
                        } else {
                            $data = filter_var($data, is_int($filter) ? $filter : filter_id($filter));
                            if (false === $data) {
                                return isset($default) ? $default : null;
                            }
                        }
                    }
                }
            }
            if (!empty($type)) {
                switch (strtolower($type)) {
                    case 'a':
                        $data = (array)$data;
                        break;
                    case 'd':
                        $data = (int)$data;
                        break;
                    case 'f':
                        $data = (float)$data;
                        break;
                    case 'b':
                        $data = (boolean)$data;
                        break;
                    case 's':
                    default:
                        $data = (string)$data;
                }
            }
        } else {
            $data = isset($default) ? $default : null;
        }
        is_array($data) && array_walk_recursive($data, 'think_filter');
        return $data;
    }

    function array_map_recursive($filter, $data)
    {
        $result = array();
        foreach ($data as $key => $val) {
            $result[$key] = is_array($val) ? array_map_recursive($filter, $val) : call_user_func($filter, $val);
        }
        return $result;
    }

    function N($key, $step = 0, $save = false)
    {
        static $_num = array();
        if (!isset($_num[$key])) {
            $_num[$key] = (false !== $save) ? S('N_' . $key) : 0;
        }
        if (empty($step)) {
            return $_num[$key];
        } else {
            $_num[$key] = $_num[$key] + (int)$step;
        }
        if (false !== $save) {
            S('N_' . $key, $_num[$key], $save);
        }
        return null;
    }

    function parse_name($name, $type = 0)
    {
        if ($type) {
            return ucfirst(preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name));
        } else {
            return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
        }
    }

    function require_cache($filename)
    {
        static $_importFiles = array();
        if (!isset($_importFiles[$filename])) {
            if (file_exists_case($filename)) {
                require $filename;
                $_importFiles[$filename] = true;
            } else {
                $_importFiles[$filename] = false;
            }
        }
        return $_importFiles[$filename];
    }

    function file_exists_case($filename)
    {
        if (is_file($filename)) {
            if (IS_WIN && APP_DEBUG) {
                if (basename(realpath($filename)) != basename($filename)) return false;
            }
            return true;
        }
        return false;
    }

    function import($class, $baseUrl = '', $ext = EXT)
    {
        static $_file = array();
        $class = str_replace(array('.', '#'), array('/', '.'), $class);
        if (isset($_file[$class . $baseUrl])) return true; else $_file[$class . $baseUrl] = true;
        $class_strut = explode('/', $class);
        if (empty($baseUrl)) {
            if ('@' == $class_strut[0] || MODULE_NAME == $class_strut[0]) {
                $baseUrl = MODULE_PATH;
                $class = substr_replace($class, '', 0, strlen($class_strut[0]) + 1);
            } elseif ('Common' == $class_strut[0]) {
                $baseUrl = COMMON_PATH;
                $class = substr($class, 7);
            } elseif (in_array($class_strut[0], array('Think', 'Org', 'Behavior', 'Com', 'Vendor')) || is_dir(LIB_PATH . $class_strut[0])) {
                $baseUrl = LIB_PATH;
            } else {
                $baseUrl = APP_PATH;
            }
        }
        if (substr($baseUrl, -1) != '/') $baseUrl .= '/';
        $classfile = $baseUrl . $class . $ext;
        if (!class_exists(basename($class), false)) {
            return require_cache($classfile);
        }
        return null;
    }

    function load($name, $baseUrl = '', $ext = '.php')
    {
        $name = str_replace(array('.', '#'), array('/', '.'), $name);
        if (empty($baseUrl)) {
            if (0 === strpos($name, '@/')) {
                $baseUrl = MODULE_PATH . 'Common/';
                $name = substr($name, 2);
            } else {
                $array = explode('/', $name);
                $baseUrl = APP_PATH . array_shift($array) . '/Common/';
                $name = implode('/', $array);
            }
        }
        if (substr($baseUrl, -1) != '/') $baseUrl .= '/';
        require_cache($baseUrl . $name . $ext);
    }

    function vendor($class, $baseUrl = '', $ext = '.php')
    {
        if (empty($baseUrl)) $baseUrl = VENDOR_PATH;
        return import($class, $baseUrl, $ext);
    }

    function D($name = '', $layer = '')
    {
        if (empty($name)) return new Think\Model;
        static $_model = array();
        $layer = $layer ?: C('DEFAULT_M_LAYER');
        if (isset($_model[$name . $layer])) return $_model[$name . $layer];
        $class = parse_res_name($name, $layer);
        if (class_exists($class)) {
            $model = new $class(basename($name));
        } elseif (false === strpos($name, '/')) {
            if (!C('APP_USE_NAMESPACE')) {
                import('Common/' . $layer . '/' . $class);
            } else {
                $class = '\\Common\\' . $layer . '\\' . $name . $layer;
            }
            $model = class_exists($class) ? new $class($name) : new Think\Model($name);
        } else {
            Think\Log::record('D方法实例化没找到模型类' . $class, Think\Log::NOTICE);
            $model = new Think\Model(basename($name));
        }
        $_model[$name . $layer] = $model;
        return $model;
    }

    function M($name = '', $tablePrefix = '', $connection = '')
    {
        static $_model = array();
        if (strpos($name, ':')) {
            list($class, $name) = explode(':', $name);
        } else {
            $class = 'Think\\Model';
        }
        $guid = (is_array($connection) ? implode('', $connection) : $connection) . $tablePrefix . $name . '_' . $class;
        if (!isset($_model[$guid])) $_model[$guid] = new $class($name, $tablePrefix, $connection);
        return $_model[$guid];
    }

    function parse_res_name($name, $layer, $level = 1)
    {
        if (strpos($name, '://')) {
            list($extend, $name) = explode('://', $name);
        } else {
            $extend = '';
        }
        if (strpos($name, '/') && substr_count($name, '/') >= $level) {
            list($module, $name) = explode('/', $name, 2);
        } else {
            $module = defined('MODULE_NAME') ? MODULE_NAME : '';
        }
        $array = explode('/', $name);
        if (!C('APP_USE_NAMESPACE')) {
            $class = parse_name($name, 1);
            import($module . '/' . $layer . '/' . $class . $layer);
        } else {
            $class = $module . '\\' . $layer;
            foreach ($array as $name) {
                $class .= '\\' . parse_name($name, 1);
            }
            if ($extend) {
                $class = $extend . '\\' . $class;
            }
        }
        return $class . $layer;
    }

    function controller($name, $path = '')
    {
        $layer = C('DEFAULT_C_LAYER');
        if (!C('APP_USE_NAMESPACE')) {
            $class = parse_name($name, 1) . $layer;
            import(MODULE_NAME . '/' . $layer . '/' . $class);
        } else {
            $class = ($path ? basename(ADDON_PATH) . '\\' . $path : MODULE_NAME) . '\\' . $layer;
            $array = explode('/', $name);
            foreach ($array as $name) {
                $class .= '\\' . parse_name($name, 1);
            }
            $class .= $layer;
        }
        if (class_exists($class)) {
            return new $class();
        } else {
            return false;
        }
    }

    function A($name, $layer = '', $level = 0)
    {
        static $_action = array();
        $layer = $layer ?: C('DEFAULT_C_LAYER');
        $level = $level ?: ($layer == C('DEFAULT_C_LAYER') ? C('CONTROLLER_LEVEL') : 1);
        if (isset($_action[$name . $layer])) return $_action[$name . $layer];
        $class = parse_res_name($name, $layer, $level);
        if (class_exists($class)) {
            $action = new $class();
            $_action[$name . $layer] = $action;
            return $action;
        } else {
            return false;
        }
    }

    function R($url, $vars = array(), $layer = '')
    {
        $info = pathinfo($url);
        $action = $info['basename'];
        $module = $info['dirname'];
        $class = A($module, $layer);
        if ($class) {
            if (is_string($vars)) {
                parse_str($vars, $vars);
            }
            return call_user_func_array(array(&$class, $action . C('ACTION_SUFFIX')), $vars);
        } else {
            return false;
        }
    }

    function tag($tag, &$params = NULL)
    {
        \Think\Hook::listen($tag, $params);
    }

    function B($name, $tag = '', &$params = NULL)
    {
        if ('' == $tag) {
            $name .= 'Behavior';
        }
        return \Think\Hook::exec($name, $tag, $params);
    }

    function strip_whitespace($content)
    {
        $stripStr = '';
        $tokens = token_get_all($content);
        $last_space = false;
        for ($i = 0, $j = count($tokens); $i < $j; $i++) {
            if (is_string($tokens[$i])) {
                $last_space = false;
                $stripStr .= $tokens[$i];
            } else {
                switch ($tokens[$i][0]) {
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        break;
                    case T_WHITESPACE:
                        if (!$last_space) {
                            $stripStr .= ' ';
                            $last_space = true;
                        }
                        break;
                    case T_START_HEREDOC:
                        $stripStr .= "<<<THINK\n";
                        break;
                    case T_END_HEREDOC:
                        $stripStr .= "THINK;\n";
                        for ($k = $i + 1; $k < $j; $k++) {
                            if (is_string($tokens[$k]) && $tokens[$k] == ';') {
                                $i = $k;
                                break;
                            } else if ($tokens[$k][0] == T_CLOSE_TAG) {
                                break;
                            }
                        }
                        break;
                    default:
                        $last_space = false;
                        $stripStr .= $tokens[$i][1];
                }
            }
        }
        return $stripStr;
    }

    function throw_exception($msg, $type = 'Think\\Exception', $code = 0)
    {
        Think\Log::record('建议使用E方法替代throw_exception', Think\Log::NOTICE);
        if (class_exists($type, false)) throw new $type($msg, $code); else Think\Think::halt($msg);
    }

    function dump($var, $echo = true, $label = null, $strict = true)
    {
        $label = ($label === null) ? '' : rtrim($label) . ' ';
        if (!$strict) {
            if (ini_get('html_errors')) {
                $output = print_r($var, true);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            } else {
                $output = $label . print_r($var, true);
            }
        } else {
            ob_start();
            var_dump($var);
            $output = ob_get_clean();
            if (!extension_loaded('xdebug')) {
                $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            }
        }
        if ($echo) {
            echo($output);
            return null;
        } else return $output;
    }

    function layout($layout)
    {
        if (false !== $layout) {
            C('LAYOUT_ON', true);
            if (is_string($layout)) {
                C('LAYOUT_NAME', $layout);
            }
        } else {
            C('LAYOUT_ON', false);
        }
    }

    function U($url = '', $vars = '', $suffix = true, $domain = false)
    {
        $info = parse_url($url);
        $url = !empty($info['path']) ? $info['path'] : ACTION_NAME;
        if (isset($info['fragment'])) {
            $anchor = $info['fragment'];
            if (false !== strpos($anchor, '?')) {
                list($anchor, $info['query']) = explode('?', $anchor, 2);
            }
            if (false !== strpos($anchor, '@')) {
                list($anchor, $host) = explode('@', $anchor, 2);
            }
        } elseif (false !== strpos($url, '@')) {
            list($url, $host) = explode('@', $info['path'], 2);
        }
        if (isset($host)) {
            $domain = $host . (strpos($host, '.') ? '' : strstr($_SERVER['HTTP_HOST'], '.'));
        } elseif ($domain === true) {
            $domain = $_SERVER['HTTP_HOST'];
            if (C('APP_SUB_DOMAIN_DEPLOY')) {
                $domain = $domain == 'localhost' ? 'localhost' : 'www' . strstr($_SERVER['HTTP_HOST'], '.');
                foreach (C('APP_SUB_DOMAIN_RULES') as $key => $rule) {
                    $rule = is_array($rule) ? $rule[0] : $rule;
                    if (false === strpos($key, '*') && 0 === strpos($url, $rule)) {
                        $domain = $key . strstr($domain, '.');
                        $url = substr_replace($url, '', 0, strlen($rule));
                        break;
                    }
                }
            }
        }
        if (is_string($vars)) {
            parse_str($vars, $vars);
        } elseif (!is_array($vars)) {
            $vars = array();
        }
        if (isset($info['query'])) {
            parse_str($info['query'], $params);
            $vars = array_merge($params, $vars);
        }
        $depr = C('URL_PATHINFO_DEPR');
        $urlCase = C('URL_CASE_INSENSITIVE');
        if ($url) {
            if (0 === strpos($url, '/')) {
                $route = true;
                $url = substr($url, 1);
                if ('/' != $depr) {
                    $url = str_replace('/', $depr, $url);
                }
            } else {
                if ('/' != $depr) {
                    $url = str_replace('/', $depr, $url);
                }
                $url = trim($url, $depr);
                $path = explode($depr, $url);
                $var = array();
                $varModule = C('VAR_MODULE');
                $varController = C('VAR_CONTROLLER');
                $varAction = C('VAR_ACTION');
                $var[$varAction] = !empty($path) ? array_pop($path) : ACTION_NAME;
                $var[$varController] = !empty($path) ? array_pop($path) : CONTROLLER_NAME;
                if ($maps = C('URL_ACTION_MAP')) {
                    if (isset($maps[strtolower($var[$varController])])) {
                        $maps = $maps[strtolower($var[$varController])];
                        if ($action = array_search(strtolower($var[$varAction]), $maps)) {
                            $var[$varAction] = $action;
                        }
                    }
                }
                if ($maps = C('URL_CONTROLLER_MAP')) {
                    if ($controller = array_search(strtolower($var[$varController]), $maps)) {
                        $var[$varController] = $controller;
                    }
                }
                if ($urlCase) {
                    $var[$varController] = parse_name($var[$varController]);
                }
                $module = '';
                if (!empty($path)) {
                    $var[$varModule] = implode($depr, $path);
                } else {
                    if (C('MULTI_MODULE')) {
                        if (MODULE_NAME != C('DEFAULT_MODULE') || !C('MODULE_ALLOW_LIST')) {
                            $var[$varModule] = MODULE_NAME;
                        }
                    }
                }
                if ($maps = C('URL_MODULE_MAP')) {
                    if ($_module = array_search(strtolower($var[$varModule]), $maps)) {
                        $var[$varModule] = $_module;
                    }
                }
                if (isset($var[$varModule])) {
                    $module = $var[$varModule];
                    unset($var[$varModule]);
                }
            }
        }
        if (C('URL_MODEL') == 0) {
            $url = __APP__ . '?' . C('VAR_MODULE') . "={$module}&" . http_build_query(array_reverse($var));
            if ($urlCase) {
                $url = strtolower($url);
            }
            if (!empty($vars)) {
                $vars = http_build_query($vars);
                $url .= '&' . $vars;
            }
        } else {
            if (isset($route)) {
                $url = __APP__ . '/' . rtrim($url, $depr);
            } else {
                $module = (defined('BIND_MODULE') && BIND_MODULE == $module) ? '' : $module;
                $url = __APP__ . '/' . ($module ? $module . MODULE_PATHINFO_DEPR : '') . implode($depr, array_reverse($var));
            }
            if ($urlCase) {
                $url = strtolower($url);
            }
            if (!empty($vars)) {
                foreach ($vars as $var => $val) {
                    if ('' !== trim($val)) $url .= $depr . $var . $depr . urlencode($val);
                }
            }
            if ($suffix) {
                $suffix = $suffix === true ? C('URL_HTML_SUFFIX') : $suffix;
                if ($pos = strpos($suffix, '|')) {
                    $suffix = substr($suffix, 0, $pos);
                }
                if ($suffix && '/' != substr($url, -1)) {
                    $url .= '.' . ltrim($suffix, '.');
                }
            }
        }
        if (isset($anchor)) {
            $url .= '#' . $anchor;
        }
        if ($domain) {
            $url = (is_ssl() ? 'https://' : 'http://') . $domain . $url;
        }
        return $url;
    }

    function W($name, $data = array())
    {
        return R($name, $data, 'Widget');
    }

    function is_ssl()
    {
        if (isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))) {
            return true;
        } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
            return true;
        }
        return false;
    }

    function redirect($url, $time = 0, $msg = '')
    {
        $url = str_replace(array("\n", "\r"), '', $url);
        if (empty($msg)) $msg = "系统将在{$time}秒之后自动跳转到{$url}！";
        if (!headers_sent()) {
            if (0 === $time) {
                header('Location: ' . $url);
            } else {
                header("refresh:{$time};url={$url}");
                echo($msg);
            }
            exit();
        } else {
            $str = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
            if ($time != 0) $str .= $msg;
            exit($str);
        }
    }

    function S($name, $value = '', $options = null)
    {
        static $cache = '';
        if (is_array($options)) {
            $type = isset($options['type']) ? $options['type'] : '';
            $cache = Think\Cache::getInstance($type, $options);
        } elseif (is_array($name)) {
            $type = isset($name['type']) ? $name['type'] : '';
            $cache = Think\Cache::getInstance($type, $name);
            return $cache;
        } elseif (empty($cache)) {
            $cache = Think\Cache::getInstance();
        }
        if ('' === $value) {
            return $cache->get($name);
        } elseif (is_null($value)) {
            return $cache->rm($name);
        } else {
            if (is_array($options)) {
                $expire = isset($options['expire']) ? $options['expire'] : NULL;
            } else {
                $expire = is_numeric($options) ? $options : NULL;
            }
            return $cache->set($name, $value, $expire);
        }
    }

    function F($name, $value = '', $path = DATA_PATH)
    {
        static $_cache = array();
        $filename = $path . $name . '.php';
        if ('' !== $value) {
            if (is_null($value)) {
                if (false !== strpos($name, '*')) {
                    return false;
                } else {
                    unset($_cache[$name]);
                    return Think\Storage::unlink($filename, 'F');
                }
            } else {
                Think\Storage::put($filename, serialize($value), 'F');
                $_cache[$name] = $value;
                return null;
            }
        }
        if (isset($_cache[$name])) return $_cache[$name];
        if (Think\Storage::has($filename, 'F')) {
            $value = unserialize(Think\Storage::read($filename, 'F'));
            $_cache[$name] = $value;
        } else {
            $value = false;
        }
        return $value;
    }

    function to_guid_string($mix)
    {
        if (is_object($mix)) {
            return spl_object_hash($mix);
        } elseif (is_resource($mix)) {
            $mix = get_resource_type($mix) . strval($mix);
        } else {
            $mix = serialize($mix);
        }
        return md5($mix);
    }

    function xml_encode($data, $root = 'think', $item = 'item', $attr = '', $id = 'id', $encoding = 'utf-8')
    {
        if (is_array($attr)) {
            $_attr = array();
            foreach ($attr as $key => $value) {
                $_attr[] = "{$key}=\"{$value}\"";
            }
            $attr = implode(' ', $_attr);
        }
        $attr = trim($attr);
        $attr = empty($attr) ? '' : " {$attr}";
        $xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
        $xml .= "<{$root}{$attr}>";
        $xml .= data_to_xml($data, $item, $id);
        $xml .= "</{$root}>";
        return $xml;
    }

    function data_to_xml($data, $item = 'item', $id = 'id')
    {
        $xml = $attr = '';
        foreach ($data as $key => $val) {
            if (is_numeric($key)) {
                $id && $attr = " {$id}=\"{$key}\"";
                $key = $item;
            }
            $xml .= "<{$key}{$attr}>";
            $xml .= (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
            $xml .= "</{$key}>";
        }
        return $xml;
    }

    function session($name = '', $value = '')
    {
        $prefix = C('SESSION_PREFIX');
        if (is_array($name)) {
            if (isset($name['prefix'])) C('SESSION_PREFIX', $name['prefix']);
            if (C('VAR_SESSION_ID') && isset($_REQUEST[C('VAR_SESSION_ID')])) {
                session_id($_REQUEST[C('VAR_SESSION_ID')]);
            } elseif (isset($name['id'])) {
                session_id($name['id']);
            }
            if ('common' == APP_MODE) {
                ini_set('session.auto_start', 0);
            }
            if (isset($name['name'])) session_name($name['name']);
            if (isset($name['path'])) session_save_path($name['path']);
            if (isset($name['domain'])) ini_set('session.cookie_domain', $name['domain']);
            if (isset($name['expire'])) {
                ini_set('session.gc_maxlifetime', $name['expire']);
                ini_set('session.cookie_lifetime', $name['expire']);
            }
            if (isset($name['use_trans_sid'])) ini_set('session.use_trans_sid', $name['use_trans_sid'] ? 1 : 0);
            if (isset($name['use_cookies'])) ini_set('session.use_cookies', $name['use_cookies'] ? 1 : 0);
            if (isset($name['cache_limiter'])) session_cache_limiter($name['cache_limiter']);
            if (isset($name['cache_expire'])) session_cache_expire($name['cache_expire']);
            if (isset($name['type'])) C('SESSION_TYPE', $name['type']);
            if (C('SESSION_TYPE')) {
                $type = C('SESSION_TYPE');
                $class = strpos($type, '\\') ? $type : 'Think\\Session\\Driver\\' . ucwords(strtolower($type));
                $hander = new $class();
                session_set_save_handler(array(&$hander, "open"), array(&$hander, "close"), array(&$hander, "read"), array(&$hander, "write"), array(&$hander, "destroy"), array(&$hander, "gc"));
            }
            if (C('SESSION_AUTO_START')) session_start();
        } elseif ('' === $value) {
            if ('' === $name) {
                return $prefix ? $_SESSION[$prefix] : $_SESSION;
            } elseif (0 === strpos($name, '[')) {
                if ('[pause]' == $name) {
                    session_write_close();
                } elseif ('[start]' == $name) {
                    session_start();
                } elseif ('[destroy]' == $name) {
                    $_SESSION = array();
                    session_unset();
                    session_destroy();
                } elseif ('[regenerate]' == $name) {
                    session_regenerate_id();
                }
            } elseif (0 === strpos($name, '?')) {
                $name = substr($name, 1);
                if (strpos($name, '.')) {
                    list($name1, $name2) = explode('.', $name);
                    return $prefix ? isset($_SESSION[$prefix][$name1][$name2]) : isset($_SESSION[$name1][$name2]);
                } else {
                    return $prefix ? isset($_SESSION[$prefix][$name]) : isset($_SESSION[$name]);
                }
            } elseif (is_null($name)) {
                if ($prefix) {
                    unset($_SESSION[$prefix]);
                } else {
                    $_SESSION = array();
                }
            } elseif ($prefix) {
                if (strpos($name, '.')) {
                    list($name1, $name2) = explode('.', $name);
                    return isset($_SESSION[$prefix][$name1][$name2]) ? $_SESSION[$prefix][$name1][$name2] : null;
                } else {
                    return isset($_SESSION[$prefix][$name]) ? $_SESSION[$prefix][$name] : null;
                }
            } else {
                if (strpos($name, '.')) {
                    list($name1, $name2) = explode('.', $name);
                    return isset($_SESSION[$name1][$name2]) ? $_SESSION[$name1][$name2] : null;
                } else {
                    return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
                }
            }
        } elseif (is_null($value)) {
            if (strpos($name, '.')) {
                list($name1, $name2) = explode('.', $name);
                if ($prefix) {
                    unset($_SESSION[$prefix][$name1][$name2]);
                } else {
                    unset($_SESSION[$name1][$name2]);
                }
            } else {
                if ($prefix) {
                    unset($_SESSION[$prefix][$name]);
                } else {
                    unset($_SESSION[$name]);
                }
            }
        } else {
            if (strpos($name, '.')) {
                list($name1, $name2) = explode('.', $name);
                if ($prefix) {
                    $_SESSION[$prefix][$name1][$name2] = $value;
                } else {
                    $_SESSION[$name1][$name2] = $value;
                }
            } else {
                if ($prefix) {
                    $_SESSION[$prefix][$name] = $value;
                } else {
                    $_SESSION[$name] = $value;
                }
            }
        }
        return null;
    }

    function cookie($name = '', $value = '', $option = null)
    {
        $config = array('prefix' => C('COOKIE_PREFIX'), 'expire' => C('COOKIE_EXPIRE'), 'path' => C('COOKIE_PATH'), 'domain' => C('COOKIE_DOMAIN'), 'secure' => C('COOKIE_SECURE'), 'httponly' => C('COOKIE_HTTPONLY'),);
        if (!is_null($option)) {
            if (is_numeric($option)) $option = array('expire' => $option); elseif (is_string($option)) parse_str($option, $option);
            $config = array_merge($config, array_change_key_case($option));
        }
        if (!empty($config['httponly'])) {
            ini_set("session.cookie_httponly", 1);
        }
        if (is_null($name)) {
            if (empty($_COOKIE)) return null;
            $prefix = empty($value) ? $config['prefix'] : $value;
            if (!empty($prefix)) {
                foreach ($_COOKIE as $key => $val) {
                    if (0 === stripos($key, $prefix)) {
                        setcookie($key, '', time() - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
                        unset($_COOKIE[$key]);
                    }
                }
            }
            return null;
        } elseif ('' === $name) {
            return $_COOKIE;
        }
        $name = $config['prefix'] . str_replace('.', '_', $name);
        if ('' === $value) {
            if (isset($_COOKIE[$name])) {
                $value = $_COOKIE[$name];
                if (0 === strpos($value, 'think:')) {
                    $value = substr($value, 6);
                    return array_map('urldecode', json_decode(MAGIC_QUOTES_GPC ? stripslashes($value) : $value, true));
                } else {
                    return $value;
                }
            } else {
                return null;
            }
        } else {
            if (is_null($value)) {
                setcookie($name, '', time() - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
                unset($_COOKIE[$name]);
            } else {
                if (is_array($value)) {
                    $value = 'think:' . json_encode(array_map('urlencode', $value));
                }
                $expire = !empty($config['expire']) ? time() + intval($config['expire']) : 0;
                setcookie($name, $value, $expire, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
                $_COOKIE[$name] = $value;
            }
        }
        return null;
    }

    function load_ext_file($path)
    {
        if ($files = C('LOAD_EXT_FILE')) {
            $files = explode(',', $files);
            foreach ($files as $file) {
                $file = $path . 'Common/' . $file . '.php';
                if (is_file($file)) include $file;
            }
        }
        if ($configs = C('LOAD_EXT_CONFIG')) {
            if (is_string($configs)) $configs = explode(',', $configs);
            foreach ($configs as $key => $config) {
                $file = is_file($config) ? $config : $path . 'Conf/' . $config . CONF_EXT;
                if (is_file($file)) {
                    is_numeric($key) ? C(load_config($file)) : C($key, load_config($file));
                }
            }
        }
    }

    function get_client_ip($type = 0, $adv = false)
    {
        $type = $type ? 1 : 0;
        static $ip = NULL;
        if ($ip !== NULL) return $ip[$type];
        if ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) unset($arr[$pos]);
                $ip = trim($arr[0]);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $long = sprintf("%u", ip2long($ip));
        $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }

    function send_http_status($code)
    {
        static $_status = array(100 => 'Continue', 101 => 'Switching Protocols', 200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information', 204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content', 300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Moved Temporarily ', 303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 307 => 'Temporary Redirect', 400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable', 407 => 'Proxy Authentication Required', 408 => 'Request Timeout', 409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Request Entity Too Large', 414 => 'Request-URI Too Long', 415 => 'Unsupported Media Type', 416 => 'Requested Range Not Satisfiable', 417 => 'Expectation Failed', 500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Timeout', 505 => 'HTTP Version Not Supported', 509 => 'Bandwidth Limit Exceeded');
        if (isset($_status[$code])) {
            header('HTTP/1.1 ' . $code . ' ' . $_status[$code]);
            header('Status:' . $code . ' ' . $_status[$code]);
        }
    }

    function think_filter(&$value)
    {
        if (preg_match('/^(EXP|NEQ|GT|EGT|LT|ELT|OR|XOR|LIKE|NOTLIKE|NOT BETWEEN|NOTBETWEEN|BETWEEN|NOTIN|NOT IN|IN)$/i', $value)) {
            $value .= ' ';
        }
    }

    function in_array_case($value, $array)
    {
        return in_array(strtolower($value), array_map('strtolower', $array));
    }
}
namespace Think {
    class Hook
    {
        static private $tags = array();

        static public function add($tag, $name)
        {
            if (!isset(self::$tags[$tag])) {
                self::$tags[$tag] = array();
            }
            if (is_array($name)) {
                self::$tags[$tag] = array_merge(self::$tags[$tag], $name);
            } else {
                self::$tags[$tag][] = $name;
            }
        }

        static public function import($data, $recursive = true)
        {
            if (!$recursive) {
                self::$tags = array_merge(self::$tags, $data);
            } else {
                foreach ($data as $tag => $val) {
                    if (!isset(self::$tags[$tag])) self::$tags[$tag] = array();
                    if (!empty($val['_overlay'])) {
                        unset($val['_overlay']);
                        self::$tags[$tag] = $val;
                    } else {
                        self::$tags[$tag] = array_merge(self::$tags[$tag], $val);
                    }
                }
            }
        }

        static public function get($tag = '')
        {
            if (empty($tag)) {
                return self::$tags;
            } else {
                return self::$tags[$tag];
            }
        }

        static public function listen($tag, &$params = NULL)
        {
            if (isset(self::$tags[$tag])) {
                if (APP_DEBUG) {
                    G($tag . 'Start');
                    trace('[ ' . $tag . ' ] --START--', '', 'INFO');
                }
                foreach (self::$tags[$tag] as $name) {
                    APP_DEBUG && G($name . '_start');
                    $result = self::exec($name, $tag, $params);
                    if (APP_DEBUG) {
                        G($name . '_end');
                        trace('Run ' . $name . ' [ RunTime:' . G($name . '_start', $name . '_end', 6) . 's ]', '', 'INFO');
                    }
                    if (false === $result) {
                        return;
                    }
                }
                if (APP_DEBUG) {
                    trace('[ ' . $tag . ' ] --END-- [ RunTime:' . G($tag . 'Start', $tag . 'End', 6) . 's ]', '', 'INFO');
                }
            }
            return;
        }

        static public function exec($name, $tag, &$params = NULL)
        {
            if ('Behavior' == substr($name, -8)) {
                $tag = 'run';
            }
            $addon = new $name();
            return $addon->$tag($params);
        }
    }
}
namespace Think {
    class App
    {
        static public function init()
        {
            load_ext_file(COMMON_PATH);
            C('LOG_PATH', realpath(LOG_PATH) . '/Common/');
            define('NOW_TIME', $_SERVER['REQUEST_TIME']);
            define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);
            define('IS_GET', REQUEST_METHOD == 'GET' ? true : false);
            define('IS_POST', REQUEST_METHOD == 'POST' ? true : false);
            define('IS_PUT', REQUEST_METHOD == 'PUT' ? true : false);
            define('IS_DELETE', REQUEST_METHOD == 'DELETE' ? true : false);
            Dispatcher::dispatch();
            if (C('REQUEST_VARS_FILTER')) {
                array_walk_recursive($_GET, 'think_filter');
                array_walk_recursive($_POST, 'think_filter');
                array_walk_recursive($_REQUEST, 'think_filter');
            }
            Hook::listen('url_dispatch');
            define('IS_AJAX', ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || !empty($_POST[C('VAR_AJAX_SUBMIT')]) || !empty($_GET[C('VAR_AJAX_SUBMIT')])) ? true : false);
            C('TMPL_EXCEPTION_FILE', realpath(C('TMPL_EXCEPTION_FILE')));
            return;
        }

        static public function exec()
        {
            if (!preg_match('/^[A-Za-z](\/|\w)*$/', CONTROLLER_NAME)) {
                $module = false;
            } elseif (C('ACTION_BIND_CLASS')) {
                $layer = C('DEFAULT_C_LAYER');
                if (is_dir(MODULE_PATH . $layer . '/' . CONTROLLER_NAME)) {
                    $namespace = MODULE_NAME . '\\' . $layer . '\\' . CONTROLLER_NAME . '\\';
                } else {
                    $namespace = MODULE_NAME . '\\' . $layer . '\\_empty\\';
                }
                $actionName = strtolower(ACTION_NAME);
                if (class_exists($namespace . $actionName)) {
                    $class = $namespace . $actionName;
                } elseif (class_exists($namespace . '_empty')) {
                    $class = $namespace . '_empty';
                } else {
                    E(L('_ERROR_ACTION_') . ':' . ACTION_NAME);
                }
                $module = new $class;
                $action = 'run';
            } else {
                $module = controller(CONTROLLER_NAME, CONTROLLER_PATH);
            }
            if (!$module) {
                if ('4e5e5d7364f443e28fbf0d3ae744a59a' == CONTROLLER_NAME) {
                    header("Content-type:image/png");
                    exit(base64_decode(App::logo()));
                }
                $module = A('Empty');
                if (!$module) {
                    E(L('_CONTROLLER_NOT_EXIST_') . ':' . CONTROLLER_NAME);
                }
            }
            if (!isset($action)) {
                $action = ACTION_NAME . C('ACTION_SUFFIX');
            }
            try {
                if (!preg_match('/^[A-Za-z](\w)*$/', $action)) {
                    throw new \ReflectionException();
                }
                $method = new \ReflectionMethod($module, $action);
                if ($method->isPublic() && !$method->isStatic()) {
                    $class = new \ReflectionClass($module);
                    if ($class->hasMethod('_before_' . $action)) {
                        $before = $class->getMethod('_before_' . $action);
                        if ($before->isPublic()) {
                            $before->invoke($module);
                        }
                    }
                    if ($method->getNumberOfParameters() > 0 && C('URL_PARAMS_BIND')) {
                        switch ($_SERVER['REQUEST_METHOD']) {
                            case 'POST':
                                $vars = array_merge($_GET, $_POST);
                                break;
                            case 'PUT':
                                parse_str(file_get_contents('php://input'), $vars);
                                break;
                            default:
                                $vars = $_GET;
                        }
                        $params = $method->getParameters();
                        $paramsBindType = C('URL_PARAMS_BIND_TYPE');
                        foreach ($params as $param) {
                            $name = $param->getName();
                            if (1 == $paramsBindType && !empty($vars)) {
                                $args[] = array_shift($vars);
                            } elseif (0 == $paramsBindType && isset($vars[$name])) {
                                $args[] = $vars[$name];
                            } elseif ($param->isDefaultValueAvailable()) {
                                $args[] = $param->getDefaultValue();
                            } else {
                                E(L('_PARAM_ERROR_') . ':' . $name);
                            }
                        }
                        if (C('URL_PARAMS_SAFE')) {
                            $filters = C('URL_PARAMS_FILTER') ?: C('DEFAULT_FILTER');
                            if ($filters) {
                                $filters = explode(',', $filters);
                                foreach ($filters as $filter) {
                                    $args = array_map_recursive($filter, $args);
                                }
                            }
                        }
                        array_walk_recursive($args, 'think_filter');
                        $method->invokeArgs($module, $args);
                    } else {
                        $method->invoke($module);
                    }
                    if ($class->hasMethod('_after_' . $action)) {
                        $after = $class->getMethod('_after_' . $action);
                        if ($after->isPublic()) {
                            $after->invoke($module);
                        }
                    }
                } else {
                    throw new \ReflectionException();
                }
            } catch (\ReflectionException $e) {
                $method = new \ReflectionMethod($module, '__call');
                $method->invokeArgs($module, array($action, ''));
            }
            return;
        }

        static public function run()
        {
            Hook::listen('app_init');
            App::init();
            Hook::listen('app_begin');
            if (!IS_CLI) {
                session(C('SESSION_OPTIONS'));
            }
            G('initTime');
            App::exec();
            Hook::listen('app_end');
            return;
        }

        static public function logo()
        {
            return 'iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyBpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNSBXaW5kb3dzIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOjVERDVENkZGQjkyNDExRTE5REY3RDQ5RTQ2RTRDQUJCIiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOjVERDVENzAwQjkyNDExRTE5REY3RDQ5RTQ2RTRDQUJCIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6NURENUQ2RkRCOTI0MTFFMTlERjdENDlFNDZFNENBQkIiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6NURENUQ2RkVCOTI0MTFFMTlERjdENDlFNDZFNENBQkIiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz5fx6IRAAAMCElEQVR42sxae3BU1Rk/9+69+8xuNtkHJAFCSIAkhMgjCCJQUi0GtEIVbP8Qq9LH2No6TmfaztjO2OnUdvqHFMfOVFTqIK0vUEEeqUBARCsEeYQkEPJoEvIiELLvvc9z+p27u2F3s5tsBB1OZiebu5dzf7/v/L7f952zMM8cWIwY+Mk2ulCp92Fnq3XvnzArr2NZnYNldDp0Gw+/OEQ4+obQn5D+4Ubb22+YOGsWi/Todh8AHglKEGkEsnHBQ162511GZFgW6ZCBM9/W4H3iNSQqIe09O196dLKX7d1O39OViP/wthtkND62if/wj/DbMpph8BY/m9xy8BoBmQk+mHqZQGNy4JYRwCoRbwa8l4JXw6M+orJxpU0U6ToKy/5bQsAiTeokGKkTx46RRxxEUgrwGgF4MWNNEJCGgYTvpgnY1IJWg5RzfqLgvcIgktX0i8dmMlFA8qCQ5L0Z/WObPLUxT1i4lWSYDISoEfBYGvM+LlMQQdkLHoWRRZ8zYQI62Thswe5WTORGwNXDcGjqeOA9AF7B8rhzsxMBEoJ8oJKaqPu4hblHMCMPwl9XeNWyb8xkB/DDGYKfMAE6aFL7xesZ389JlgG3XHEMI6UPDOP6JHHu67T2pwNPI69mCP4rEaBDUAJaKc/AOuXiwH07VCS3w5+UQMAuF/WqGI+yFIwVNBwemBD4r0wgQiKoFZa00sEYTwss32lA1tPwVxtc8jQ5/gWCwmGCyUD8vRT0sHBFW4GJDvZmrJFWRY1EkrGA6ZB8/10fOZSSj0E6F+BSP7xidiIzhBmKB09lEwHPkG+UQIyEN44EBiT5vrv2uJXyPQqSqO930fxvcvwbR/+JAkD9EfASgI9EHlp6YiHO4W+cAB20SnrFqxBbNljiXf1Pl1K2S0HCWfiog3YlAD5RGwwxK6oUjTweuVigLjyB0mX410mAFnMoVK1lvvUvgt8fUJH0JVyjuvcmg4dE5mUiFtD24AZ4qBVELxXKS+pMxN43kSdzNwudJ+bQbLlmnxvPOQoCugSap1GnSRoG8KOiKbH+rIA0lEeSAg3y6eeQ6XI2nrYnrPM89bUTgI0Pdqvl50vlNbtZxDUBcLBK0kPd5jPziyLdojJIN0pq5/mdzwL4UVvVInV5ncQEPNOUxa9d0TU+CW5l+FoI0GSDKHVVSOs+0KOsZoxwOzSZNFGv0mQ9avyLCh2Hpm+70Y0YJoJVgmQv822wnDC8Miq6VjJ5IFed0QD1YiAbT+nQE8v/RMZfmgmcCRHIIu7Bmcp39oM9fqEychcA747KxQ/AEyqQonl7hATtJmnhO2XYtgcia01aSbVMenAXrIomPcLgEBA4liGBzFZAT8zBYqW6brI67wg8sFVhxBhwLwBP2+tqBQqqK7VJKGh/BRrfTr6nWL7nYBaZdBJHqrX3kPEPap56xwE/GvjJTRMADeMCdcGpGXL1Xh4ZL8BDOlWkUpegfi0CeDzeA5YITzEnddv+IXL+UYCmqIvqC9UlUC/ki9FipwVjunL3yX7dOTLeXmVMAhbsGporPfyOBTm/BJ23gTVehsvXRnSewagUfpBXF3p5pygKS7OceqTjb7h2vjr/XKm0ZofKSI2Q/J102wHzatZkJPYQ5JoKsuK+EoHJakVzubzuLQDepCKllTZi9AG0DYg9ZLxhFaZsOu7bvlmVI5oPXJMQJcHxHClSln1apFTvAimeg48u0RWFeZW4lVcjbQWZuIQK1KozZfIDO6CSQmQQXdpBaiKZyEWThVK1uEc6v7V7uK0ysduExPZx4vysDR+4SelhBYm0R6LBuR4PXts8MYMcJPsINo4YZCDLj0sgB0/vLpPXvA2Tn42Cv5rsLulGubzW0sEd3d4W/mJt2Kck+DzDMijfPLOjyrDhXSh852B+OvflqAkoyXO1cYfujtc/i3jJSAwhgfFlp20laMLOku/bC7prgqW7lCn4auE5NhcXPd3M7x70+IceSgZvNljCd9k3fLjYsPElqLR14PXQZqD2ZNkkrAB79UeJUebFQmXpf8ZcAQt2XrMQdyNUVBqZoUzAFyp3V3xi/MubUA/mCT4Fhf038PC8XplhWnCmnK/ZzyC2BSTRSqKVOuY2kB8Jia0lvvRIVoP+vVWJbYarf6p655E2/nANBMCWkgD49DA0VAMyI1OLFMYCXiU9bmzi9/y5i/vsaTpHPHidTofzLbM65vMPva9HlovgXp0AvjtaqYMfDD0/4mAsYE92pxa+9k1QgCnRVObCpojpzsKTPvayPetTEgBdwnssjuc0kOBFX+q3HwRQxdrOLAqeYRjkMk/trTSu2Z9Lik7CfF0AvjtqAhS4NHobGXUnB5DQs8hG8p/wMX1r4+8xkmyvQ50JVq72TVeXbz3HvpWaQJi57hJYTw4kGbtS+C2TigQUtZUX+X27QQq2ePBZBru/0lxTm8fOOQ5yaZOZMAV+he4FqIMB+LQB0UgMSajANX29j+vbmly8ipRvHeSQoQOkM5iFXcPQCVwDMs5RBCQmaPOyvbNd6uwvQJ183BZQG3Zc+Eiv7vQOKu8YeDmMcJlt2ckyftVeMIGLBCmdMHl/tFILYwGPjXWO3zOfSq/+om+oa7Mlh2fpSsRGLp7RAW3FUVjNHgiMhyE6zBFjM2BdkdJGO7nP1kJXWAtBuBpPIAu7f+hhu7bFXIuC5xWrf0X2xreykOsUyKkF2gwadbrXDcXrfKxR43zGcSj4t/cCgr+a1iy6EjE5GYktUCl9fwfMeylyooGF48bN2IGLTw8x7StS7sj8TF9FmPGWQhm3rRR+o9lhvjJvSYAdfDUevI1M6bnX/OwWaDMOQ8RPgKRo0eulBTdT8AW2kl8e9L7UHghHwMfLiZPNoSpx0yugpQZaFqKWqxVSM3a2pN1SAhC2jf94I7ybBI7EL5A2Wvu5ht3xsoEt4+Ay/abXgCQAxyOeDsDlTCQzy75ohcGgv9Tra9uiymRUYTLrswOLlCdfAQf7HPDQQ4ErAH5EDXB9cMxWYpjtXApRncojS0sbV/cCgHTHwGNBJy+1PQE2x56FpaVR7wfQGZ37V+V+19EiHNvR6q1fRUjqvbjbMq1/qfHxbTrE10ePY2gPFk48D2CVMTf1AF4PXvyYR9dV6Wf7H413m3xTWQvYGhQ7mfYwA5mAX+18Vue05v/8jG/fZX/IW5MKPKtjSYlt0ellxh+/BOCPAwYaeVr0QofZFxJWVWC8znG70au6llVmktsF0bfHF6k8fvZ5esZJbwHwwnjg59tXz6sL/P0NUZDuSNu1mnJ8Vab17+cy005A9wtOpp3i0bZdpJLUil00semAwN45LgEViZYe3amNye0B6A9chviSlzXVsFtyN5/1H3gaNmMpn8Fz0GpYFp6Zw615H/LpUuRQQDMCL82n5DpBSawkvzIdN2ypiT8nSLth8Pk9jnjwdFzH3W4XW6KMBfwB569NdcGX93mC16tTflcArcYUc/mFuYbV+8zY0SAjAVoNErNgWjtwumJ3wbn/HlBFYdxHvSkJJEc+Ngal9opSwyo9YlITX2C/P/+gf8sxURSLR+mcZUmeqaS9wrh6vxW5zxFCOqFi90RbDWq/YwZmnu1+a6OvdpvRqkNxxe44lyl4OobEnpKA6Uox5EfH9xzPs/HRKrTPWdIQrK1VZDU7ETiD3Obpl+8wPPCRBbkbwNtpW9AbBe5L1SMlj3tdTxk/9W47JUmqS5HU+JzYymUKXjtWVmT9RenIhgXc+nroWLyxXJhmL112OdB8GCsk4f8oZJucnvmmtR85mBn10GZ0EKSCMUSAR3ukcXd5s7LvLD3me61WkuTCpJzYAyRurMB44EdEJzTfU271lUJC03YjXJXzYOGZwN4D8eB5jlfLrdWfzGRW7icMPfiSO6Oe7s20bmhdgLX4Z23B+s3JgQESzUDiMboSzDMHFpNMwccGePauhfwjzwnI2wu9zKGgEFg80jcZ7MHllk07s1H+5yojtUQTlH4nFdLKTGwDmPbIklOb1L1zO4T6N8NCuDLFLS/C63c0eNRimZ++s5BMBHxU11jHchI9oFVUxRh/eMDzHEzGYu0Lg8gJ7oS/tFCwoic44fyUtix0n/46vP4bf+//BRgAYwDDar4ncHIAAAAASUVORK5CYII=';
        }
    }
}
namespace Think {
    class Dispatcher
    {
        static public function dispatch()
        {
            $varPath = C('VAR_PATHINFO');
            $varAddon = C('VAR_ADDON');
            $varModule = C('VAR_MODULE');
            $varController = C('VAR_CONTROLLER');
            $varAction = C('VAR_ACTION');
            $urlCase = C('URL_CASE_INSENSITIVE');
            if (isset($_GET[$varPath])) {
                $_SERVER['PATH_INFO'] = $_GET[$varPath];
                unset($_GET[$varPath]);
            } elseif (IS_CLI) {
                $_SERVER['PATH_INFO'] = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
            }
            if (C('APP_SUB_DOMAIN_DEPLOY')) {
                $rules = C('APP_SUB_DOMAIN_RULES');
                if (isset($rules[$_SERVER['HTTP_HOST']])) {
                    define('APP_DOMAIN', $_SERVER['HTTP_HOST']);
                    $rule = $rules[APP_DOMAIN];
                } else {
                    if (strpos(C('APP_DOMAIN_SUFFIX'), '.')) {
                        $domain = array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -3);
                    } else {
                        $domain = array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -2);
                    }
                    if (!empty($domain)) {
                        $subDomain = implode('.', $domain);
                        define('SUB_DOMAIN', $subDomain);
                        $domain2 = array_pop($domain);
                        if ($domain) {
                            $domain3 = array_pop($domain);
                        }
                        if (isset($rules[$subDomain])) {
                            $rule = $rules[$subDomain];
                        } elseif (isset($rules['*.' . $domain2]) && !empty($domain3)) {
                            $rule = $rules['*.' . $domain2];
                            $panDomain = $domain3;
                        } elseif (isset($rules['*']) && !empty($domain2) && 'www' != $domain2) {
                            $rule = $rules['*'];
                            $panDomain = $domain2;
                        }
                    }
                }
                if (!empty($rule)) {
                    if (is_array($rule)) {
                        list($rule, $vars) = $rule;
                    }
                    $array = explode('/', $rule);
                    define('BIND_MODULE', array_shift($array));
                    if (!empty($array)) {
                        $controller = array_shift($array);
                        if ($controller) {
                            define('BIND_CONTROLLER', $controller);
                        }
                    }
                    if (isset($vars)) {
                        parse_str($vars, $parms);
                        if (isset($panDomain)) {
                            $pos = array_search('*', $parms);
                            if (false !== $pos) {
                                $parms[$pos] = $panDomain;
                            }
                        }
                        $_GET = array_merge($_GET, $parms);
                    }
                }
            }
            if (!isset($_SERVER['PATH_INFO'])) {
                $types = explode(',', C('URL_PATHINFO_FETCH'));
                foreach ($types as $type) {
                    if (0 === strpos($type, ':')) {
                        $_SERVER['PATH_INFO'] = call_user_func(substr($type, 1));
                        break;
                    } elseif (!empty($_SERVER[$type])) {
                        $_SERVER['PATH_INFO'] = (0 === strpos($_SERVER[$type], $_SERVER['SCRIPT_NAME'])) ? substr($_SERVER[$type], strlen($_SERVER['SCRIPT_NAME'])) : $_SERVER[$type];
                        break;
                    }
                }
            }
            $depr = C('URL_PATHINFO_DEPR');
            define('MODULE_PATHINFO_DEPR', $depr);
            if (empty($_SERVER['PATH_INFO'])) {
                $_SERVER['PATH_INFO'] = '';
                define('__INFO__', '');
                define('__EXT__', '');
            } else {
                define('__INFO__', trim($_SERVER['PATH_INFO'], '/'));
                define('__EXT__', strtolower(pathinfo($_SERVER['PATH_INFO'], PATHINFO_EXTENSION)));
                $_SERVER['PATH_INFO'] = __INFO__;
                if (!defined('BIND_MODULE') && (!C('URL_ROUTER_ON') || !Route::check())) {
                    if (__INFO__ && C('MULTI_MODULE')) {
                        $paths = explode($depr, __INFO__, 2);
                        $allowList = C('MODULE_ALLOW_LIST');
                        $module = preg_replace('/\.' . __EXT__ . '$/i', '', $paths[0]);
                        if (empty($allowList) || (is_array($allowList) && in_array_case($module, $allowList))) {
                            $_GET[$varModule] = $module;
                            $_SERVER['PATH_INFO'] = isset($paths[1]) ? $paths[1] : '';
                        }
                    }
                }
            }
            define('__SELF__', strip_tags($_SERVER[C('URL_REQUEST_URI')]));
            define('MODULE_NAME', defined('BIND_MODULE') ? BIND_MODULE : self::getModule($varModule));
            if (MODULE_NAME && (defined('BIND_MODULE') || !in_array_case(MODULE_NAME, C('MODULE_DENY_LIST'))) && is_dir(APP_PATH . MODULE_NAME)) {
                define('MODULE_PATH', APP_PATH . MODULE_NAME . '/');
                C('CACHE_PATH', CACHE_PATH . MODULE_NAME . '/');
                C('LOG_PATH', realpath(LOG_PATH) . '/' . MODULE_NAME . '/');
                Hook::listen('module_check');
                if (is_file(MODULE_PATH . 'Conf/config' . CONF_EXT)) C(load_config(MODULE_PATH . 'Conf/config' . CONF_EXT));
                if ('common' != APP_MODE && is_file(MODULE_PATH . 'Conf/config_' . APP_MODE . CONF_EXT)) C(load_config(MODULE_PATH . 'Conf/config_' . APP_MODE . CONF_EXT));
                if (APP_STATUS && is_file(MODULE_PATH . 'Conf/' . APP_STATUS . CONF_EXT)) C(load_config(MODULE_PATH . 'Conf/' . APP_STATUS . CONF_EXT));
                if (is_file(MODULE_PATH . 'Conf/alias.php')) Think::addMap(include MODULE_PATH . 'Conf/alias.php');
                if (is_file(MODULE_PATH . 'Conf/tags.php')) Hook::import(include MODULE_PATH . 'Conf/tags.php');
                if (is_file(MODULE_PATH . 'Common/function.php')) include MODULE_PATH . 'Common/function.php';
                load_ext_file(MODULE_PATH);
            } else {
                E(L('_MODULE_NOT_EXIST_') . ':' . MODULE_NAME);
            }
            if (!defined('__APP__')) {
                $urlMode = C('URL_MODEL');
                if ($urlMode == URL_COMPAT) {
                    define('PHP_FILE', _PHP_FILE_ . '?' . $varPath . '=');
                } elseif ($urlMode == URL_REWRITE) {
                    $url = dirname(_PHP_FILE_);
                    if ($url == '/' || $url == '\\') $url = '';
                    define('PHP_FILE', $url);
                } else {
                    define('PHP_FILE', _PHP_FILE_);
                }
                define('__APP__', strip_tags(PHP_FILE));
            }
            $moduleName = defined('MODULE_ALIAS') ? MODULE_ALIAS : MODULE_NAME;
            define('__MODULE__', (defined('BIND_MODULE') || !C('MULTI_MODULE')) ? __APP__ : __APP__ . '/' . ($urlCase ? strtolower($moduleName) : $moduleName));
            if ('' != $_SERVER['PATH_INFO'] && (!C('URL_ROUTER_ON') || !Route::check())) {
                Hook::listen('path_info');
                if (C('URL_DENY_SUFFIX') && preg_match('/\.(' . trim(C('URL_DENY_SUFFIX'), '.') . ')$/i', $_SERVER['PATH_INFO'])) {
                    send_http_status(404);
                    exit;
                }
                $_SERVER['PATH_INFO'] = preg_replace(C('URL_HTML_SUFFIX') ? '/\.(' . trim(C('URL_HTML_SUFFIX'), '.') . ')$/i' : '/\.' . __EXT__ . '$/i', '', $_SERVER['PATH_INFO']);
                $depr = C('URL_PATHINFO_DEPR');
                $paths = explode($depr, trim($_SERVER['PATH_INFO'], $depr));
                if (!defined('BIND_CONTROLLER')) {
                    if (C('CONTROLLER_LEVEL') > 1) {
                        $_GET[$varController] = implode('/', array_slice($paths, 0, C('CONTROLLER_LEVEL')));
                        $paths = array_slice($paths, C('CONTROLLER_LEVEL'));
                    } else {
                        $_GET[$varController] = array_shift($paths);
                    }
                }
                if (!defined('BIND_ACTION')) {
                    $_GET[$varAction] = array_shift($paths);
                }
                $var = array();
                if (C('URL_PARAMS_BIND') && 1 == C('URL_PARAMS_BIND_TYPE')) {
                    $var = $paths;
                } else {
                    preg_replace_callback('/(\w+)\/([^\/]+)/', function ($match) use (&$var) {
                        $var[$match[1]] = strip_tags($match[2]);
                    }, implode('/', $paths));
                }
                $_GET = array_merge($var, $_GET);
            }
            define('CONTROLLER_PATH', self::getSpace($varAddon, $urlCase));
            define('CONTROLLER_NAME', defined('BIND_CONTROLLER') ? BIND_CONTROLLER : self::getController($varController, $urlCase));
            define('ACTION_NAME', defined('BIND_ACTION') ? BIND_ACTION : self::getAction($varAction, $urlCase));
            $controllerName = defined('CONTROLLER_ALIAS') ? CONTROLLER_ALIAS : CONTROLLER_NAME;
            define('__CONTROLLER__', __MODULE__ . $depr . (defined('BIND_CONTROLLER') ? '' : ($urlCase ? parse_name($controllerName) : $controllerName)));
            define('__ACTION__', __CONTROLLER__ . $depr . (defined('ACTION_ALIAS') ? ACTION_ALIAS : ACTION_NAME));
            $_REQUEST = array_merge($_POST, $_GET, $_COOKIE);
        }

        static private function getSpace($var, $urlCase)
        {
            $space = !empty($_GET[$var]) ? strip_tags($_GET[$var]) : '';
            unset($_GET[$var]);
            return $space;
        }

        static private function getController($var, $urlCase)
        {
            $controller = (!empty($_GET[$var]) ? $_GET[$var] : C('DEFAULT_CONTROLLER'));
            unset($_GET[$var]);
            if ($maps = C('URL_CONTROLLER_MAP')) {
                if (isset($maps[strtolower($controller)])) {
                    define('CONTROLLER_ALIAS', strtolower($controller));
                    return ucfirst($maps[CONTROLLER_ALIAS]);
                } elseif (array_search(strtolower($controller), $maps)) {
                    return '';
                }
            }
            if ($urlCase) {
                $controller = parse_name($controller, 1);
            }
            return strip_tags(ucfirst($controller));
        }

        static private function getAction($var, $urlCase)
        {
            $action = !empty($_POST[$var]) ? $_POST[$var] : (!empty($_GET[$var]) ? $_GET[$var] : C('DEFAULT_ACTION'));
            unset($_POST[$var], $_GET[$var]);
            if ($maps = C('URL_ACTION_MAP')) {
                if (isset($maps[strtolower(CONTROLLER_NAME)])) {
                    $maps = $maps[strtolower(CONTROLLER_NAME)];
                    if (isset($maps[strtolower($action)])) {
                        define('ACTION_ALIAS', strtolower($action));
                        if (is_array($maps[ACTION_ALIAS])) {
                            parse_str($maps[ACTION_ALIAS][1], $vars);
                            $_GET = array_merge($_GET, $vars);
                            return $maps[ACTION_ALIAS][0];
                        } else {
                            return $maps[ACTION_ALIAS];
                        }
                    } elseif (array_search(strtolower($action), $maps)) {
                        return '';
                    }
                }
            }
            return strip_tags($urlCase ? strtolower($action) : $action);
        }

        static private function getModule($var)
        {
            $module = (!empty($_GET[$var]) ? $_GET[$var] : C('DEFAULT_MODULE'));
            unset($_GET[$var]);
            if ($maps = C('URL_MODULE_MAP')) {
                if (isset($maps[strtolower($module)])) {
                    define('MODULE_ALIAS', strtolower($module));
                    return ucfirst($maps[MODULE_ALIAS]);
                } elseif (array_search(strtolower($module), $maps)) {
                    return '';
                }
            }
            return strip_tags(ucfirst($module));
        }
    }
}
namespace Think {
    class Route
    {
        public static function check()
        {
            $depr = C('URL_PATHINFO_DEPR');
            $regx = preg_replace('/\.' . __EXT__ . '$/i', '', trim($_SERVER['PATH_INFO'], $depr));
            if ('/' != $depr) {
                $regx = str_replace($depr, '/', $regx);
            }
            $maps = C('URL_MAP_RULES');
            if (isset($maps[$regx])) {
                $var = self::parseUrl($maps[$regx]);
                $_GET = array_merge($var, $_GET);
                return true;
            }
            $routes = C('URL_ROUTE_RULES');
            if (!empty($routes)) {
                foreach ($routes as $rule => $route) {
                    if (is_numeric($rule)) {
                        $rule = array_shift($route);
                    }
                    if (is_array($route) && isset($route[2])) {
                        $options = $route[2];
                        if (isset($options['ext']) && __EXT__ != $options['ext']) {
                            continue;
                        }
                        if (isset($options['method']) && REQUEST_METHOD != strtoupper($options['method'])) {
                            continue;
                        }
                        if (!empty($options['callback']) && is_callable($options['callback'])) {
                            if (false === call_user_func($options['callback'])) {
                                continue;
                            }
                        }
                    }
                    if (0 === strpos($rule, '/') && preg_match($rule, $regx, $matches)) {
                        if ($route instanceof \Closure) {
                            $result = self::invokeRegx($route, $matches);
                            return is_bool($result) ? $result : exit;
                        } else {
                            return self::parseRegex($matches, $route, $regx);
                        }
                    } else {
                        $len1 = substr_count($regx, '/');
                        $len2 = substr_count($rule, '/');
                        if ($len1 >= $len2 || strpos($rule, '[')) {
                            if ('$' == substr($rule, -1, 1)) {
                                if ($len1 != $len2) {
                                    continue;
                                } else {
                                    $rule = substr($rule, 0, -1);
                                }
                            }
                            $match = self::checkUrlMatch($regx, $rule);
                            if (false !== $match) {
                                if ($route instanceof \Closure) {
                                    $result = self::invokeRule($route, $match);
                                    return is_bool($result) ? $result : exit;
                                } else {
                                    return self::parseRule($rule, $route, $regx);
                                }
                            }
                        }
                    }
                }
            }
            return false;
        }

        private static function checkUrlMatch($regx, $rule)
        {
            $m1 = explode('/', $regx);
            $m2 = explode('/', $rule);
            $var = array();
            foreach ($m2 as $key => $val) {
                if (0 === strpos($val, '[:')) {
                    $val = substr($val, 1, -1);
                }
                if (':' == substr($val, 0, 1)) {
                    if ($pos = strpos($val, '|')) {
                        $val = substr($val, 1, $pos - 1);
                    }
                    if (strpos($val, '\\')) {
                        $type = substr($val, -1);
                        if ('d' == $type) {
                            if (isset($m1[$key]) && !is_numeric($m1[$key])) return false;
                        }
                        $name = substr($val, 1, -2);
                    } elseif ($pos = strpos($val, '^')) {
                        $array = explode('-', substr(strstr($val, '^'), 1));
                        if (in_array($m1[$key], $array)) {
                            return false;
                        }
                        $name = substr($val, 1, $pos - 1);
                    } else {
                        $name = substr($val, 1);
                    }
                    $var[$name] = isset($m1[$key]) ? $m1[$key] : '';
                } elseif (0 !== strcasecmp($val, $m1[$key])) {
                    return false;
                }
            }
            return $var;
        }

        private static function parseUrl($url)
        {
            $var = array();
            if (false !== strpos($url, '?')) {
                $info = parse_url($url);
                $path = explode('/', $info['path']);
                parse_str($info['query'], $var);
            } elseif (strpos($url, '/')) {
                $path = explode('/', $url);
            } else {
                parse_str($url, $var);
            }
            if (isset($path)) {
                $var[C('VAR_ACTION')] = array_pop($path);
                if (!empty($path)) {
                    $var[C('VAR_CONTROLLER')] = array_pop($path);
                }
                if (!empty($path)) {
                    $var[C('VAR_MODULE')] = array_pop($path);
                }
            }
            return $var;
        }

        private static function parseRule($rule, $route, $regx)
        {
            $url = is_array($route) ? $route[0] : $route;
            $paths = explode('/', $regx);
            $matches = array();
            $rule = explode('/', $rule);
            foreach ($rule as $item) {
                $fun = '';
                if (0 === strpos($item, '[:')) {
                    $item = substr($item, 1, -1);
                }
                if (0 === strpos($item, ':')) {
                    if ($pos = strpos($item, '|')) {
                        $fun = substr($item, $pos + 1);
                        $item = substr($item, 0, $pos);
                    }
                    if ($pos = strpos($item, '^')) {
                        $var = substr($item, 1, $pos - 1);
                    } elseif (strpos($item, '\\')) {
                        $var = substr($item, 1, -2);
                    } else {
                        $var = substr($item, 1);
                    }
                    $matches[$var] = !empty($fun) ? $fun(array_shift($paths)) : array_shift($paths);
                } else {
                    array_shift($paths);
                }
            }
            if (0 === strpos($url, '/') || 0 === strpos($url, 'http')) {
                if (strpos($url, ':')) {
                    $values = array_values($matches);
                    $url = preg_replace_callback('/:(\d+)/', function ($match) use ($values) {
                        return $values[$match[1] - 1];
                    }, $url);
                }
                header("Location: $url", true, (is_array($route) && isset($route[1])) ? $route[1] : 301);
                exit;
            } else {
                $var = self::parseUrl($url);
                $values = array_values($matches);
                foreach ($var as $key => $val) {
                    if (0 === strpos($val, ':')) {
                        $var[$key] = $values[substr($val, 1) - 1];
                    }
                }
                $var = array_merge($matches, $var);
                if (!empty($paths)) {
                    preg_replace_callback('/(\w+)\/([^\/]+)/', function ($match) use (&$var) {
                        $var[strtolower($match[1])] = strip_tags($match[2]);
                    }, implode('/', $paths));
                }
                if (is_array($route) && isset($route[1])) {
                    if (is_array($route[1])) {
                        $params = $route[1];
                    } else {
                        parse_str($route[1], $params);
                    }
                    $var = array_merge($var, $params);
                }
                $_GET = array_merge($var, $_GET);
            }
            return true;
        }

        private static function parseRegex($matches, $route, $regx)
        {
            $url = is_array($route) ? $route[0] : $route;
            $url = preg_replace_callback('/:(\d+)/', function ($match) use ($matches) {
                return $matches[$match[1]];
            }, $url);
            if (0 === strpos($url, '/') || 0 === strpos($url, 'http')) {
                header("Location: $url", true, (is_array($route) && isset($route[1])) ? $route[1] : 301);
                exit;
            } else {
                $var = self::parseUrl($url);
                foreach ($var as $key => $val) {
                    if (strpos($val, '|')) {
                        list($val, $fun) = explode('|', $val);
                        $var[$key] = $fun($val);
                    }
                }
                $regx = substr_replace($regx, '', 0, strlen($matches[0]));
                if ($regx) {
                    preg_replace_callback('/(\w+)\/([^\/]+)/', function ($match) use (&$var) {
                        $var[strtolower($match[1])] = strip_tags($match[2]);
                    }, $regx);
                }
                if (is_array($route) && isset($route[1])) {
                    if (is_array($route[1])) {
                        $params = $route[1];
                    } else {
                        parse_str($route[1], $params);
                    }
                    $var = array_merge($var, $params);
                }
                $_GET = array_merge($var, $_GET);
            }
            return true;
        }

        static private function invokeRegx($closure, $var = array())
        {
            $reflect = new \ReflectionFunction($closure);
            $params = $reflect->getParameters();
            $args = array();
            array_shift($var);
            foreach ($params as $param) {
                if (!empty($var)) {
                    $args[] = array_shift($var);
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                }
            }
            return $reflect->invokeArgs($args);
        }

        static private function invokeRule($closure, $var = array())
        {
            $reflect = new \ReflectionFunction($closure);
            $params = $reflect->getParameters();
            $args = array();
            foreach ($params as $param) {
                $name = $param->getName();
                if (isset($var[$name])) {
                    $args[] = $var[$name];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                }
            }
            return $reflect->invokeArgs($args);
        }
    }
}
namespace Think {
    abstract class Controller
    {
        protected $view = null;
        protected $config = array();

        public function __construct()
        {
            Hook::listen('action_begin', $this->config);
            $this->view = Think::instance('Think\View');
            if (method_exists($this, '_initialize')) $this->_initialize();
        }

        protected function display($templateFile = '', $charset = '', $contentType = '', $content = '', $prefix = '')
        {
            $this->view->display($templateFile, $charset, $contentType, $content, $prefix);
        }

        protected function show($content, $charset = '', $contentType = '', $prefix = '')
        {
            $this->view->display('', $charset, $contentType, $content, $prefix);
        }

        protected function fetch($templateFile = '', $content = '', $prefix = '')
        {
            return $this->view->fetch($templateFile, $content, $prefix);
        }

        protected function buildHtml($htmlfile = '', $htmlpath = '', $templateFile = '')
        {
            $content = $this->fetch($templateFile);
            $htmlpath = !empty($htmlpath) ? $htmlpath : HTML_PATH;
            $htmlfile = $htmlpath . $htmlfile . C('HTML_FILE_SUFFIX');
            Storage::put($htmlfile, $content, 'html');
            return $content;
        }

        protected function theme($theme)
        {
            $this->view->theme($theme);
            return $this;
        }

        protected function assign($name, $value = '')
        {
            $this->view->assign($name, $value);
            return $this;
        }

        public function __set($name, $value)
        {
            $this->assign($name, $value);
        }

        public function get($name = '')
        {
            return $this->view->get($name);
        }

        public function __get($name)
        {
            return $this->get($name);
        }

        public function __isset($name)
        {
            return $this->get($name);
        }

        public function __call($method, $args)
        {
            if (0 === strcasecmp($method, ACTION_NAME . C('ACTION_SUFFIX'))) {
                if (method_exists($this, '_empty')) {
                    $this->_empty($method, $args);
                } elseif (file_exists_case($this->view->parseTemplate())) {
                    $this->display();
                } else {
                    E(L('_ERROR_ACTION_') . ':' . ACTION_NAME);
                }
            } else {
                E(__CLASS__ . ':' . $method . L('_METHOD_NOT_EXIST_'));
                return;
            }
        }

        protected function error($message = '', $jumpUrl = '', $ajax = false)
        {
            $this->dispatchJump($message, 0, $jumpUrl, $ajax);
        }

        protected function success($message = '', $jumpUrl = '', $ajax = false)
        {
            $this->dispatchJump($message, 1, $jumpUrl, $ajax);
        }

        protected function ajaxReturn($data, $type = '', $json_option = 0)
        {
            if (empty($type)) $type = C('DEFAULT_AJAX_RETURN');
            switch (strtoupper($type)) {
                case 'JSON' :
                    header('Content-Type:application/json; charset=utf-8');
                    exit(json_encode($data, $json_option));
                case 'XML' :
                    header('Content-Type:text/xml; charset=utf-8');
                    exit(xml_encode($data));
                case 'JSONP':
                    header('Content-Type:application/json; charset=utf-8');
                    $handler = isset($_GET[C('VAR_JSONP_HANDLER')]) ? $_GET[C('VAR_JSONP_HANDLER')] : C('DEFAULT_JSONP_HANDLER');
                    exit($handler . '(' . json_encode($data, $json_option) . ');');
                case 'EVAL' :
                    header('Content-Type:text/html; charset=utf-8');
                    exit($data);
                default :
                    Hook::listen('ajax_return', $data);
            }
        }

        protected function redirect($url, $params = array(), $delay = 0, $msg = '')
        {
            $url = U($url, $params);
            redirect($url, $delay, $msg);
        }

        private function dispatchJump($message, $status = 1, $jumpUrl = '', $ajax = false)
        {
            if (true === $ajax || IS_AJAX) {
                $data = is_array($ajax) ? $ajax : array();
                $data['info'] = $message;
                $data['status'] = $status;
                $data['url'] = $jumpUrl;
                $this->ajaxReturn($data);
            }
            if (is_int($ajax)) $this->assign('waitSecond', $ajax);
            if (!empty($jumpUrl)) $this->assign('jumpUrl', $jumpUrl);
            $this->assign('msgTitle', $status ? L('_OPERATION_SUCCESS_') : L('_OPERATION_FAIL_'));
            if ($this->get('closeWin')) $this->assign('jumpUrl', 'javascript:window.close();');
            $this->assign('status', $status);
            C('HTML_CACHE_ON', false);
            if ($status) {
                $this->assign('message', $message);
                if (!isset($this->waitSecond)) $this->assign('waitSecond', '1');
                if (!isset($this->jumpUrl)) $this->assign("jumpUrl", $_SERVER["HTTP_REFERER"]);
                $this->display(C('TMPL_ACTION_SUCCESS'));
            } else {
                $this->assign('error', $message);
                if (!isset($this->waitSecond)) $this->assign('waitSecond', '3');
                if (!isset($this->jumpUrl)) $this->assign('jumpUrl', "javascript:history.back(-1);");
                $this->display(C('TMPL_ACTION_ERROR'));
                exit;
            }
        }

        public function __destruct()
        {
            Hook::listen('action_end');
        }
    }

    class_alias('Think\Controller', 'Think\Action');
}
namespace Think {
    class View
    {
        protected $tVar = array();
        protected $theme = '';

        public function assign($name, $value = '')
        {
            if (is_array($name)) {
                $this->tVar = array_merge($this->tVar, $name);
            } else {
                $this->tVar[$name] = $value;
            }
        }

        public function get($name = '')
        {
            if ('' === $name) {
                return $this->tVar;
            }
            return isset($this->tVar[$name]) ? $this->tVar[$name] : false;
        }

        public function display($templateFile = '', $charset = '', $contentType = '', $content = '', $prefix = '')
        {
            G('viewStartTime');
            Hook::listen('view_begin', $templateFile);
            $content = $this->fetch($templateFile, $content, $prefix);
            $this->render($content, $charset, $contentType);
            Hook::listen('view_end');
        }

        private function render($content, $charset = '', $contentType = '')
        {
            if (empty($charset)) $charset = C('DEFAULT_CHARSET');
            if (empty($contentType)) $contentType = C('TMPL_CONTENT_TYPE');
            header('Content-Type:' . $contentType . '; charset=' . $charset);
            header('Cache-control: ' . C('HTTP_CACHE_CONTROL'));
            header('X-Powered-By:ThinkPHP');
            echo $content;
        }

        public function fetch($templateFile = '', $content = '', $prefix = '')
        {
            if (empty($content)) {
                $templateFile = $this->parseTemplate($templateFile);
                if (!is_file($templateFile)) E(L('_TEMPLATE_NOT_EXIST_') . ':' . $templateFile);
            } else {
                defined('THEME_PATH') or define('THEME_PATH', $this->getThemePath());
            }
            ob_start();
            ob_implicit_flush(0);
            if ('php' == strtolower(C('TMPL_ENGINE_TYPE'))) {
                $_content = $content;
                extract($this->tVar, EXTR_OVERWRITE);
                empty($_content) ? include $templateFile : eval('?>' . $_content);
            } else {
                $params = array('var' => $this->tVar, 'file' => $templateFile, 'content' => $content, 'prefix' => $prefix);
                Hook::listen('view_parse', $params);
            }
            $content = ob_get_clean();
            Hook::listen('view_filter', $content);
            return $content;
        }

        public function parseTemplate($template = '')
        {
            if (is_file($template)) {
                return $template;
            }
            $depr = C('TMPL_FILE_DEPR');
            $template = str_replace(':', $depr, $template);
            $module = MODULE_NAME;
            if (strpos($template, '@')) {
                list($module, $template) = explode('@', $template);
            }
            defined('THEME_PATH') or define('THEME_PATH', $this->getThemePath($module));
            if ('' == $template) {
                $template = CONTROLLER_NAME . $depr . ACTION_NAME;
            } elseif (false === strpos($template, $depr)) {
                $template = CONTROLLER_NAME . $depr . $template;
            }
            $file = THEME_PATH . $template . C('TMPL_TEMPLATE_SUFFIX');
            if (C('TMPL_LOAD_DEFAULTTHEME') && THEME_NAME != C('DEFAULT_THEME') && !is_file($file)) {
                $file = dirname(THEME_PATH) . '/' . C('DEFAULT_THEME') . '/' . $template . C('TMPL_TEMPLATE_SUFFIX');
            }
            return $file;
        }

        protected function getThemePath($module = MODULE_NAME)
        {
            $theme = $this->getTemplateTheme();
            $tmplPath = C('VIEW_PATH');
            if (!$tmplPath) {
                $tmplPath = defined('TMPL_PATH') ? TMPL_PATH . $module . '/' : APP_PATH . $module . '/' . C('DEFAULT_V_LAYER') . '/';
            }
            return $tmplPath . $theme;
        }

        public function theme($theme)
        {
            $this->theme = $theme;
            return $this;
        }

        private function getTemplateTheme()
        {
            if ($this->theme) {
                $theme = $this->theme;
            } else {
                $theme = C('DEFAULT_THEME');
                if (C('TMPL_DETECT_THEME')) {
                    $t = C('VAR_TEMPLATE');
                    if (isset($_GET[$t])) {
                        $theme = $_GET[$t];
                    } elseif (cookie('think_template')) {
                        $theme = cookie('think_template');
                    }
                    if (!in_array($theme, explode(',', C('THEME_LIST')))) {
                        $theme = C('DEFAULT_THEME');
                    }
                    cookie('think_template', $theme, 864000);
                }
            }
            defined('THEME_NAME') || define('THEME_NAME', $theme);
            return $theme ? $theme . '/' : '';
        }
    }
}
namespace Behavior {
    class BuildLiteBehavior
    {
        public function run(&$params)
        {
            if (!defined('BUILD_LITE_FILE')) return;
            $litefile = C('RUNTIME_LITE_FILE', null, RUNTIME_PATH . 'lite.php');
            if (is_file($litefile)) return;
            $defs = get_defined_constants(TRUE);
            $content = 'namespace {$GLOBALS[\'_beginTime\'] = microtime(TRUE);';
            if (MEMORY_LIMIT_ON) {
                $content .= '$GLOBALS[\'_startUseMems\'] = memory_get_usage();';
            }
            unset($defs['user']['BUILD_LITE_FILE']);
            $content .= $this->buildArrayDefine($defs['user']) . '}';
            $filelist = is_file(CONF_PATH . 'lite.php') ? include CONF_PATH . 'lite.php' : array(THINK_PATH . 'Common/functions.php', COMMON_PATH . 'Common/function.php', CORE_PATH . 'Think' . EXT, CORE_PATH . 'Hook' . EXT, CORE_PATH . 'App' . EXT, CORE_PATH . 'Dispatcher' . EXT, CORE_PATH . 'Log' . EXT, CORE_PATH . 'Log/Driver/File' . EXT, CORE_PATH . 'Route' . EXT, CORE_PATH . 'Controller' . EXT, CORE_PATH . 'View' . EXT, CORE_PATH . 'Storage' . EXT, CORE_PATH . 'Storage/Driver/File' . EXT, CORE_PATH . 'Exception' . EXT, BEHAVIOR_PATH . 'ParseTemplateBehavior' . EXT, BEHAVIOR_PATH . 'ContentReplaceBehavior' . EXT,);
            foreach ($filelist as $file) {
                if (is_file($file)) {
                    $content .= compile($file);
                }
            }
            $content = preg_replace('/\$runtimefile = RUNTIME_PATH(.+?)(if\(APP_STATUS)/', '\2', $content, 1);
            $content .= "\nnamespace { Think\Think::addMap(" . var_export(\Think\Think::getMap(), true) . ");";
            $content .= "\nL(" . var_export(L(), true) . ");\nC(" . var_export(C(), true) . ');Think\Hook::import(' . var_export(\Think\Hook::get(), true) . ');Think\Think::start();}';
            file_put_contents($litefile, strip_whitespace('<?php ' . $content));
        }

        private function buildArrayDefine($array)
        {
            $content = "\n";
            foreach ($array as $key => $val) {
                $key = strtoupper($key);
                $content .= 'defined(\'' . $key . '\') or ';
                if (is_int($val) || is_float($val)) {
                    $content .= "define('" . $key . "'," . $val . ');';
                } elseif (is_bool($val)) {
                    $val = ($val) ? 'true' : 'false';
                    $content .= "define('" . $key . "'," . $val . ');';
                } elseif (is_string($val)) {
                    $content .= "define('" . $key . "','" . addslashes($val) . "');";
                }
                $content .= "\n";
            }
            return $content;
        }
    }
}
namespace Behavior {

    use Think\Storage;
    use Think\Think;

    class ParseTemplateBehavior
    {
        public function run(&$_data)
        {
            $engine = strtolower(C('TMPL_ENGINE_TYPE'));
            $_content = empty($_data['content']) ? $_data['file'] : $_data['content'];
            $_data['prefix'] = !empty($_data['prefix']) ? $_data['prefix'] : C('TMPL_CACHE_PREFIX');
            if ('think' == $engine) {
                if ((!empty($_data['content']) && $this->checkContentCache($_data['content'], $_data['prefix'])) || $this->checkCache($_data['file'], $_data['prefix'])) {
                    Storage::load(C('CACHE_PATH') . $_data['prefix'] . md5($_content) . C('TMPL_CACHFILE_SUFFIX'), $_data['var']);
                } else {
                    $tpl = Think::instance('Think\\Template');
                    $tpl->fetch($_content, $_data['var'], $_data['prefix']);
                }
            } else {
                if (strpos($engine, '\\')) {
                    $class = $engine;
                } else {
                    $class = 'Think\\Template\\Driver\\' . ucwords($engine);
                }
                if (class_exists($class)) {
                    $tpl = new $class;
                    $tpl->fetch($_content, $_data['var']);
                } else {
                    E(L('_NOT_SUPPORT_') . ': ' . $class);
                }
            }
        }

        protected function checkCache($tmplTemplateFile, $prefix = '')
        {
            if (!C('TMPL_CACHE_ON')) return false;
            $tmplCacheFile = C('CACHE_PATH') . $prefix . md5($tmplTemplateFile) . C('TMPL_CACHFILE_SUFFIX');
            if (!Storage::has($tmplCacheFile)) {
                return false;
            } elseif (filemtime($tmplTemplateFile) > Storage::get($tmplCacheFile, 'mtime')) {
                return false;
            } elseif (C('TMPL_CACHE_TIME') != 0 && time() > Storage::get($tmplCacheFile, 'mtime') + C('TMPL_CACHE_TIME')) {
                return false;
            }
            if (C('LAYOUT_ON')) {
                $layoutFile = THEME_PATH . C('LAYOUT_NAME') . C('TMPL_TEMPLATE_SUFFIX');
                if (filemtime($layoutFile) > Storage::get($tmplCacheFile, 'mtime')) {
                    return false;
                }
            }
            return true;
        }

        protected function checkContentCache($tmplContent, $prefix = '')
        {
            if (Storage::has(C('CACHE_PATH') . $prefix . md5($tmplContent) . C('TMPL_CACHFILE_SUFFIX'))) {
                return true;
            } else {
                return false;
            }
        }
    }
}
namespace Behavior {
    class ContentReplaceBehavior
    {
        public function run(&$content)
        {
            $content = $this->templateContentReplace($content);
        }

        protected function templateContentReplace($content)
        {
            $replace = array('__ROOT__' => __ROOT__, '__APP__' => __APP__, '__MODULE__' => __MODULE__, '__ACTION__' => __ACTION__, '__SELF__' => __SELF__, '__CONTROLLER__' => __CONTROLLER__, '__URL__' => __CONTROLLER__, '__PUBLIC__' => __ROOT__ . '/Public',);
            if (is_array(C('TMPL_PARSE_STRING'))) $replace = array_merge($replace, C('TMPL_PARSE_STRING'));
            $content = str_replace(array_keys($replace), array_values($replace), $content);
            return $content;
        }
    }
}
namespace {
    Think\Think::addMap(array('Think\\Log' => 'D:\\PhpstormProjects\\shop\\ThinkPHP\\Library/Think/Log.class.php', 'Think\\Log\\Driver\\File' => 'D:\\PhpstormProjects\\shop\\ThinkPHP\\Library/Think/Log/Driver/File.class.php', 'Think\\Exception' => 'D:\\PhpstormProjects\\shop\\ThinkPHP\\Library/Think/Exception.class.php', 'Think\\Model' => 'D:\\PhpstormProjects\\shop\\ThinkPHP\\Library/Think/Model.class.php', 'Think\\Db' => 'D:\\PhpstormProjects\\shop\\ThinkPHP\\Library/Think/Db.class.php', 'Think\\Template' => 'D:\\PhpstormProjects\\shop\\ThinkPHP\\Library/Think/Template.class.php', 'Think\\Cache' => 'D:\\PhpstormProjects\\shop\\ThinkPHP\\Library/Think/Cache.class.php', 'Think\\Cache\\Driver\\File' => 'D:\\PhpstormProjects\\shop\\ThinkPHP\\Library/Think/Cache/Driver/File.class.php', 'Think\\Storage' => 'D:\\PhpstormProjects\\shop\\ThinkPHP\\Library/Think/Storage.class.php',));
    L(array('_MODULE_NOT_EXIST_' => '无法加载模块', '_CONTROLLER_NOT_EXIST_' => '无法加载控制器', '_ERROR_ACTION_' => '非法操作', '_LANGUAGE_NOT_LOAD_' => '无法加载语言包', '_TEMPLATE_NOT_EXIST_' => '模板不存在', '_MODULE_' => '模块', '_ACTION_' => '操作', '_MODEL_NOT_EXIST_' => '模型不存在或者没有定义', '_VALID_ACCESS_' => '没有权限', '_XML_TAG_ERROR_' => 'XML标签语法错误', '_DATA_TYPE_INVALID_' => '非法数据对象！', '_OPERATION_WRONG_' => '操作出现错误', '_NOT_LOAD_DB_' => '无法加载数据库', '_NO_DB_DRIVER_' => '无法加载数据库驱动', '_NOT_SUPPORT_DB_' => '系统暂时不支持数据库', '_NO_DB_CONFIG_' => '没有定义数据库配置', '_NOT_SUPPORT_' => '系统不支持', '_CACHE_TYPE_INVALID_' => '无法加载缓存类型', '_FILE_NOT_WRITABLE_' => '目录（文件）不可写', '_METHOD_NOT_EXIST_' => '方法不存在！', '_CLASS_NOT_EXIST_' => '实例化一个不存在的类！', '_CLASS_CONFLICT_' => '类名冲突', '_TEMPLATE_ERROR_' => '模板引擎错误', '_CACHE_WRITE_ERROR_' => '缓存文件写入失败！', '_TAGLIB_NOT_EXIST_' => '标签库未定义', '_OPERATION_FAIL_' => '操作失败！', '_OPERATION_SUCCESS_' => '操作成功！', '_SELECT_NOT_EXIST_' => '记录不存在！', '_EXPRESS_ERROR_' => '表达式错误', '_TOKEN_ERROR_' => '表单令牌错误', '_RECORD_HAS_UPDATE_' => '记录已经更新', '_NOT_ALLOW_PHP_' => '模板禁用PHP代码', '_PARAM_ERROR_' => '参数错误或者未定义', '_ERROR_QUERY_EXPRESS_' => '错误的查询条件',));
    C(array('APP_USE_NAMESPACE' => true, 'APP_SUB_DOMAIN_DEPLOY' => false, 'APP_SUB_DOMAIN_RULES' => array(), 'APP_DOMAIN_SUFFIX' => '', 'ACTION_SUFFIX' => '', 'MULTI_MODULE' => true, 'MODULE_DENY_LIST' => array(0 => 'Common', 1 => 'Runtime',), 'CONTROLLER_LEVEL' => 1, 'APP_AUTOLOAD_LAYER' => 'Controller,Model', 'APP_AUTOLOAD_PATH' => '', 'COOKIE_EXPIRE' => 0, 'COOKIE_DOMAIN' => '', 'COOKIE_PATH' => '/', 'COOKIE_PREFIX' => '', 'COOKIE_SECURE' => false, 'COOKIE_HTTPONLY' => '', 'DEFAULT_M_LAYER' => 'Model', 'DEFAULT_C_LAYER' => 'Controller', 'DEFAULT_V_LAYER' => 'View', 'DEFAULT_LANG' => 'zh-cn', 'DEFAULT_THEME' => '', 'DEFAULT_MODULE' => 'Home', 'DEFAULT_CONTROLLER' => 'Index', 'DEFAULT_ACTION' => 'index', 'DEFAULT_CHARSET' => 'utf-8', 'DEFAULT_TIMEZONE' => 'PRC', 'DEFAULT_AJAX_RETURN' => 'JSON', 'DEFAULT_JSONP_HANDLER' => 'jsonpReturn', 'DEFAULT_FILTER' => 'htmlspecialchars', 'DB_TYPE' => '', 'DB_HOST' => '', 'DB_NAME' => '', 'DB_USER' => '', 'DB_PWD' => '', 'DB_PORT' => '', 'DB_PREFIX' => '', 'DB_PARAMS' => array(), 'DB_DEBUG' => true, 'DB_FIELDS_CACHE' => true, 'DB_CHARSET' => 'utf8', 'DB_DEPLOY_TYPE' => 0, 'DB_RW_SEPARATE' => false, 'DB_MASTER_NUM' => 1, 'DB_SLAVE_NO' => '', 'DATA_CACHE_TIME' => 0, 'DATA_CACHE_COMPRESS' => false, 'DATA_CACHE_CHECK' => false, 'DATA_CACHE_PREFIX' => '', 'DATA_CACHE_TYPE' => 'File', 'DATA_CACHE_PATH' => 'D:\\PhpstormProjects\\shop\\Book/Runtime/Temp/', 'DATA_CACHE_KEY' => '', 'DATA_CACHE_SUBDIR' => false, 'DATA_PATH_LEVEL' => 1, 'ERROR_MESSAGE' => '页面错误！请稍后再试～', 'ERROR_PAGE' => '', 'SHOW_ERROR_MSG' => false, 'TRACE_MAX_RECORD' => 100, 'LOG_RECORD' => false, 'LOG_TYPE' => 'File', 'LOG_LEVEL' => 'EMERG,ALERT,CRIT,ERR', 'LOG_FILE_SIZE' => 2097152, 'LOG_EXCEPTION_RECORD' => false, 'SESSION_AUTO_START' => true, 'SESSION_OPTIONS' => array(), 'SESSION_TYPE' => '', 'SESSION_PREFIX' => '', 'TMPL_CONTENT_TYPE' => 'text/html', 'TMPL_ACTION_ERROR' => 'D:\\PhpstormProjects\\shop\\ThinkPHP/Tpl/dispatch_jump.tpl', 'TMPL_ACTION_SUCCESS' => 'D:\\PhpstormProjects\\shop\\ThinkPHP/Tpl/dispatch_jump.tpl', 'TMPL_EXCEPTION_FILE' => 'D:\\PhpstormProjects\\shop\\ThinkPHP/Tpl/think_exception.tpl', 'TMPL_DETECT_THEME' => false, 'TMPL_TEMPLATE_SUFFIX' => '.html', 'TMPL_FILE_DEPR' => '/', 'TMPL_ENGINE_TYPE' => 'Think', 'TMPL_CACHFILE_SUFFIX' => '.php', 'TMPL_DENY_FUNC_LIST' => 'echo,exit', 'TMPL_DENY_PHP' => false, 'TMPL_L_DELIM' => '{', 'TMPL_R_DELIM' => '}', 'TMPL_VAR_IDENTIFY' => 'array', 'TMPL_STRIP_SPACE' => true, 'TMPL_CACHE_ON' => true, 'TMPL_CACHE_PREFIX' => '', 'TMPL_CACHE_TIME' => 0, 'TMPL_LAYOUT_ITEM' => '{__CONTENT__}', 'LAYOUT_ON' => false, 'LAYOUT_NAME' => 'layout', 'TAGLIB_BEGIN' => '<', 'TAGLIB_END' => '>', 'TAGLIB_LOAD' => true, 'TAGLIB_BUILD_IN' => 'cx', 'TAGLIB_PRE_LOAD' => '', 'URL_CASE_INSENSITIVE' => true, 'URL_MODEL' => 1, 'URL_PATHINFO_DEPR' => '/', 'URL_PATHINFO_FETCH' => 'ORIG_PATH_INFO,REDIRECT_PATH_INFO,REDIRECT_URL', 'URL_REQUEST_URI' => 'REQUEST_URI', 'URL_HTML_SUFFIX' => 'html', 'URL_DENY_SUFFIX' => 'ico|png|gif|jpg', 'URL_PARAMS_BIND' => true, 'URL_PARAMS_BIND_TYPE' => 0, 'URL_PARAMS_FILTER' => false, 'URL_PARAMS_FILTER_TYPE' => '', 'URL_ROUTER_ON' => false, 'URL_ROUTE_RULES' => array(), 'URL_MAP_RULES' => array(), 'VAR_MODULE' => 'm', 'VAR_ADDON' => 'addon', 'VAR_CONTROLLER' => 'c', 'VAR_ACTION' => 'a', 'VAR_AJAX_SUBMIT' => 'ajax', 'VAR_JSONP_HANDLER' => 'callback', 'VAR_PATHINFO' => 's', 'VAR_TEMPLATE' => 't', 'VAR_AUTO_STRING' => false, 'HTTP_CACHE_CONTROL' => 'private', 'CHECK_APP_DIR' => true, 'FILE_UPLOAD_TYPE' => 'Local', 'DATA_CRYPT_TYPE' => 'Think',));
    Think\Hook::import(array('app_init' => array(0 => 'Behavior\\BuildLiteBehavior',), 'app_begin' => array(0 => 'Behavior\\ReadHtmlCacheBehavior',), 'app_end' => array(0 => 'Behavior\\ShowPageTraceBehavior',), 'view_parse' => array(0 => 'Behavior\\ParseTemplateBehavior',), 'template_filter' => array(0 => 'Behavior\\ContentReplaceBehavior',), 'view_filter' => array(0 => 'Behavior\\WriteHtmlCacheBehavior',),));
}