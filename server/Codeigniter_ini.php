<?php
    /**
     * CodeIgniter
     *
     * An open source application development framework for PHP
     *
     * This content is released under the MIT License (MIT)
     *
     * Copyright (c) 2014 - 2017, British Columbia Institute of Technology
     *
     * Permission is hereby granted, free of charge, to any person obtaining a copy
     * of this software and associated documentation files (the "Software"), to deal
     * in the Software without restriction, including without limitation the rights
     * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
     * copies of the Software, and to permit persons to whom the Software is
     * furnished to do so, subject to the following conditions:
     *
     * The above copyright notice and this permission notice shall be included in
     * all copies or substantial portions of the Software.
     *
     * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
     * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
     * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
     * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
     * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
     * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
     * THE SOFTWARE.
     *
     * @package	CodeIgniter
     * @author	EllisLab Dev Team
     * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
     * @copyright	Copyright (c) 2014 - 2017, British Columbia Institute of Technology (http://bcit.ca/)
     * @license	http://opensource.org/licenses/MIT	MIT License
     * @link	https://codeigniter.com
     * @since	Version 1.0.0
     * @filesource
     */
    defined('BASEPATH') OR exit('No direct script access allowed');

    /**
     * System Initialization File
     *
     * Loads the base classes and executes the request.
     *
     * @package		CodeIgniter
     * @subpackage	CodeIgniter
     * @category	Front-controller
     * @author		EllisLab Dev Team
     * @link		https://codeigniter.com/user_guide/
     */

    /**
     * CodeIgniter Version
     *
     * @var	string
     *
     */
    const CI_VERSION = '3.1.5-dev';

    /*
     * ------------------------------------------------------
     *  Load the framework constants
     * ------------------------------------------------------
     */
    if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/constants.php'))
    {
        require_once(APPPATH.'config/'.ENVIRONMENT.'/constants.php');
    }

    if (file_exists(APPPATH.'config/constants.php'))
    {
        require_once(APPPATH.'config/constants.php');
    }

    /*
     * ------------------------------------------------------
     *  Load the global functions
     * ------------------------------------------------------
     */
    require_once(BASEPATH.'core/Common.php');


    /*
     * ------------------------------------------------------
     * Security procedures
     * ------------------------------------------------------
     */

    if ( ! is_php('5.4'))
    {
        ini_set('magic_quotes_runtime', 0);

        if ((bool) ini_get('register_globals'))
        {
            $_protected = array(
                '_SERVER',
                '_GET',
                '_POST',
                '_FILES',
                '_REQUEST',
                '_SESSION',
                '_ENV',
                '_COOKIE',
                'GLOBALS',
                'HTTP_RAW_POST_DATA',
                'system_path',
                'application_folder',
                'view_folder',
                '_protected',
                '_registered'
            );

            $_registered = ini_get('variables_order');
            foreach (array('E' => '_ENV', 'G' => '_GET', 'P' => '_POST', 'C' => '_COOKIE', 'S' => '_SERVER') as $key => $superglobal)
            {
                if (strpos($_registered, $key) === FALSE)
                {
                    continue;
                }

                foreach (array_keys($$superglobal) as $var)
                {
                    if (isset($GLOBALS[$var]) && ! in_array($var, $_protected, TRUE))
                    {
                        $GLOBALS[$var] = NULL;
                    }
                }
            }
        }
    }


    /*
     * ------------------------------------------------------
     *  Define a custom error handler so we can log PHP errors
     * ------------------------------------------------------
     */
    set_error_handler('_error_handler');
    set_exception_handler('_exception_handler');
    register_shutdown_function('_shutdown_handler');

    /*
     * ------------------------------------------------------
     *  Set the subclass_prefix
     * ------------------------------------------------------
     *
     * Normally the "subclass_prefix" is set in the config file.
     * The subclass prefix allows CI to know if a core class is
     * being extended via a library in the local application
     * "libraries" folder. Since CI allows config items to be
     * overridden via data set in the main index.php file,
     * before proceeding we need to know if a subclass_prefix
     * override exists. If so, we will set this value now,
     * before any classes are loaded
     * Note: Since the config file data is cached it doesn't
     * hurt to load it here.
     */
    if ( ! empty($assign_to_config['subclass_prefix']))
    {
        get_config(array('subclass_prefix' => $assign_to_config['subclass_prefix']));
    }

    /*
     * ------------------------------------------------------
     *  Should we use a Composer autoloader?
     * ------------------------------------------------------
     */
    if ($composer_autoload = config_item('composer_autoload'))
    {
        if ($composer_autoload === TRUE)
        {
            file_exists(APPPATH.'vendor/autoload.php')
                ? require_once(APPPATH.'vendor/autoload.php')
                : log_message('error', '$config[\'composer_autoload\'] is set to TRUE but '.APPPATH.'vendor/autoload.php was not found.');
        }
        elseif (file_exists($composer_autoload))
        {
            require_once($composer_autoload);
        }
        else
        {
            log_message('error', 'Could not find the specified $config[\'composer_autoload\'] path: '.$composer_autoload);
        }
    }

    /*
     * ------------------------------------------------------
     *  Load the app controller and local controller
     * ------------------------------------------------------
     *
     */
    // Load the base controller class
    require_once BASEPATH.'core/Controller.php';

    /**
     * Reference to the CI_Controller method.
     *
     * Returns current CI instance object
     *
     * @return CI_Controller
     */
    function &get_instance()
    {
        return CI_Controller::get_instance();
    }

    /*
     * ------------------------------------------------------
     * Important charset-related stuff
     * ------------------------------------------------------
     *
     * Configure mbstring and/or iconv if they are enabled
     * and set MB_ENABLED and ICONV_ENABLED constants, so
     * that we don't repeatedly do extension_loaded() or
     * function_exists() calls.
     *
     * Note: UTF-8 class depends on this. It used to be done
     * in it's constructor, but it's _not_ class-specific.
     *
     */
    $charset = strtoupper(config_item('charset'));
    ini_set('default_charset', $charset);

    if (extension_loaded('mbstring'))
    {
        define('MB_ENABLED', TRUE);
        // mbstring.internal_encoding is deprecated starting with PHP 5.6
        // and it's usage triggers E_DEPRECATED messages.
        @ini_set('mbstring.internal_encoding', $charset);
        // This is required for mb_convert_encoding() to strip invalid characters.
        // That's utilized by CI_Utf8, but it's also done for consistency with iconv.
        mb_substitute_character('none');
    }
    else
    {
        define('MB_ENABLED', FALSE);
    }

    // There's an ICONV_IMPL constant, but the PHP manual says that using
    // iconv's predefined constants is "strongly discouraged".
    if (extension_loaded('iconv'))
    {
        define('ICONV_ENABLED', TRUE);
        // iconv.internal_encoding is deprecated starting with PHP 5.6
        // and it's usage triggers E_DEPRECATED messages.
        @ini_set('iconv.internal_encoding', $charset);
    }
    else
    {
        define('ICONV_ENABLED', FALSE);
    }

    if (is_php('5.6'))
    {
        ini_set('php.internal_encoding', $charset);
    }

    /*
     * ------------------------------------------------------
     *  Load compatibility features
     * ------------------------------------------------------
     */

    require_once(BASEPATH.'core/compat/mbstring.php');
    require_once(BASEPATH.'core/compat/hash.php');
    require_once(BASEPATH.'core/compat/password.php');
    require_once(BASEPATH.'core/compat/standard.php');
