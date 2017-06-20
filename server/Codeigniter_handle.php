<?php
    /*
     * ------------------------------------------------------
     *  Start the timer... tick tock tick tock...
     * ------------------------------------------------------
     */
    $BM =& load_class('Benchmark', 'core');
    $BM->mark('total_execution_time_start');
    $BM->mark('loading_time:_base_classes_start');

    /*
     * ------------------------------------------------------
     *  Instantiate the hooks class
     * ------------------------------------------------------
     */
    $EXT =& load_class('Hooks', 'core');

    /*
     * ------------------------------------------------------
     *  Is there a "pre_system" hook?
     * ------------------------------------------------------
     */
    $EXT->call_hook('pre_system');

    /*
     * ------------------------------------------------------
     *  Instantiate the config class
     * ------------------------------------------------------
     *
     * Note: It is important that Config is loaded first as
     * most other classes depend on it either directly or by
     * depending on another class that uses it.
     *
     */
    $CFG =& load_class('Config', 'core');

    // Do we have any manually set config items in the index.php file?
    if (isset($assign_to_config) && is_array($assign_to_config))
    {
        foreach ($assign_to_config as $key => $value)
        {
            $CFG->set_item($key, $value);
        }
    }

    /*
     * ------------------------------------------------------
     *  Instantiate the UTF-8 class
     * ------------------------------------------------------
     */
    $UNI =& load_class('Utf8', 'core');

    /*
     * ------------------------------------------------------
     *  Instantiate the URI class
     * ------------------------------------------------------
     */
    $URI =& load_class('URI', 'core');

    /*
     * ------------------------------------------------------
     *  Instantiate the routing class and set the routing
     * ------------------------------------------------------
     */
    $RTR =& load_class('Router', 'core', isset($routing) ? $routing : NULL);

    /*
     * ------------------------------------------------------
     *  Instantiate the output class
     * ------------------------------------------------------
     */
    $OUT =& load_class('Output', 'core');

    /*
     * ------------------------------------------------------
     *	Is there a valid cache file? If so, we're done...
     * ------------------------------------------------------
     */
    if ($EXT->call_hook('cache_override') === FALSE && $OUT->_display_cache($CFG, $URI) === TRUE)
    {
        exit;
    }

    /*
     * -----------------------------------------------------
     * Load the security class for xss and csrf support
     * -----------------------------------------------------
     */
    $SEC =& load_class('Security', 'core');

    /*
     * ------------------------------------------------------
     *  Load the Input class and sanitize globals
     * ------------------------------------------------------
     */
    $IN	=& load_class('Input', 'core');

    /*
     * ------------------------------------------------------
     *  Load the Language class
     * ------------------------------------------------------
     */
    $LANG =& load_class('Lang', 'core');


    if (file_exists(APPPATH.'core/'.$CFG->config['subclass_prefix'].'Controller.php'))
    {
        require_once APPPATH.'core/'.$CFG->config['subclass_prefix'].'Controller.php';
    }

    // Set a mark point for benchmarking
    $BM->mark('loading_time:_base_classes_end');

    /*
     * ------------------------------------------------------
     *  Sanity checks
     * ------------------------------------------------------
     *
     *  The Router class has already validated the request,
     *  leaving us with 3 options here:
     *
     *	1) an empty class name, if we reached the default
     *	   controller, but it didn't exist;
     *	2) a query string which doesn't go through a
     *	   file_exists() check
     *	3) a regular request for a non-existing page
     *
     *  We handle all of these as a 404 error.
     *
     *  Furthermore, none of the methods in the app controller
     *  or the loader class can be called via the URI, nor can
     *  controller methods that begin with an underscore.
     */

    $e404 = FALSE;
    $class = ucfirst($RTR->class);
    $method = $RTR->method;

    if (empty($class) OR ! file_exists(APPPATH.'controllers/'.$RTR->directory.$class.'.php'))
    {
        $e404 = TRUE;
    }
    else
    {
        require_once(APPPATH.'controllers/'.$RTR->directory.$class.'.php');

        if ( ! class_exists($class, FALSE) OR $method[0] === '_' OR method_exists('CI_Controller', $method))
        {
            $e404 = TRUE;
        }
        elseif (method_exists($class, '_remap'))
        {
            $params = array($method, array_slice($URI->rsegments, 2));
            $method = '_remap';
        }
        elseif ( ! method_exists($class, $method))
        {
            $e404 = TRUE;
        }
        /**
         * DO NOT CHANGE THIS, NOTHING ELSE WORKS!
         *
         * - method_exists() returns true for non-public methods, which passes the previous elseif
         * - is_callable() returns false for PHP 4-style constructors, even if there's a __construct()
         * - method_exists($class, '__construct') won't work because CI_Controller::__construct() is inherited
         * - People will only complain if this doesn't work, even though it is documented that it shouldn't.
         *
         * ReflectionMethod::isConstructor() is the ONLY reliable check,
         * knowing which method will be executed as a constructor.
         */
        elseif ( ! is_callable(array($class, $method)))
        {
            $reflection = new ReflectionMethod($class, $method);
            if ( ! $reflection->isPublic() OR $reflection->isConstructor())
            {
                $e404 = TRUE;
            }
        }
    }

    if ($e404)
    {
        if ( ! empty($RTR->routes['404_override']))
        {
            if (sscanf($RTR->routes['404_override'], '%[^/]/%s', $error_class, $error_method) !== 2)
            {
                $error_method = 'index';
            }

            $error_class = ucfirst($error_class);

            if ( ! class_exists($error_class, FALSE))
            {
                if (file_exists(APPPATH.'controllers/'.$RTR->directory.$error_class.'.php'))
                {
                    require_once(APPPATH.'controllers/'.$RTR->directory.$error_class.'.php');
                    $e404 = ! class_exists($error_class, FALSE);
                }
                // Were we in a directory? If so, check for a global override
                elseif ( ! empty($RTR->directory) && file_exists(APPPATH.'controllers/'.$error_class.'.php'))
                {
                    require_once(APPPATH.'controllers/'.$error_class.'.php');
                    if (($e404 = ! class_exists($error_class, FALSE)) === FALSE)
                    {
                        $RTR->directory = '';
                    }
                }
            }
            else
            {
                $e404 = FALSE;
            }
        }
        // Did we reset the $e404 flag? If so, set the rsegments, starting from index 1
        if ( ! $e404)
        {
            $class = $error_class;
            $method = $error_method;

            $URI->rsegments = array(
                1 => $class,
                2 => $method
            );
        }
        else
        {
            show_404($RTR->directory.$class.'/'.$method);
            return;
        }
    }

    if ($method !== '_remap')
    {
        $params = array_slice($URI->rsegments, 2);
    }

    /*
     * ------------------------------------------------------
     *  Is there a "pre_controller" hook?
     * ------------------------------------------------------
     */
    $EXT->call_hook('pre_controller');

    /*
     * ------------------------------------------------------
     *  Instantiate the requested controller
     * ------------------------------------------------------
     */
    // Mark a start point so we can benchmark the controller
    $BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_start');

    $CI = new $class();

    /*
     * ------------------------------------------------------
     *  Is there a "post_controller_constructor" hook?
     * ------------------------------------------------------
     */
    $EXT->call_hook('post_controller_constructor');

    /*
     * ------------------------------------------------------
     *  Call the requested method
     * ------------------------------------------------------
     */
    call_user_func_array(array(&$CI, $method), $params);

    // Mark a benchmark end point
    $BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_end');

    /*
     * ------------------------------------------------------
     *  Is there a "post_controller" hook?
     * ------------------------------------------------------
     */
    $EXT->call_hook('post_controller');

    /*
     * ------------------------------------------------------
     *  Send the final rendered output to the browser
     * ------------------------------------------------------
     */
    if ($EXT->call_hook('display_override') === FALSE)
    {
        $OUT->_display();
    }

    /*
     * ------------------------------------------------------
     *  Is there a "post_system" hook?
     * ------------------------------------------------------
     */
    $EXT->call_hook('post_system');
