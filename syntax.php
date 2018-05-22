<?php
//
// Original source of this plugin is PukiWiki.
// Ported by Hokkaidoperson
//
//
// Original Licenses of this plugin:
//
// $Id: online.inc.php,v 1.12 2007/02/10 06:21:53 henoheno Exp $
// Copyright (C)
//   2002-2005, 2007 PukiWiki Developers Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// Online plugin -- Just show the number 'users-on-line'

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_onlinenumber extends DokuWiki_Syntax_Plugin {

    function getType(){
        return 'substition';
    }

    function getSort(){
        return 160;
    }


        // Internal functions

        // Check I am already online (recorded and not time-out)
        // & $count == Number of online users
        function plugin_online_check_online(& $count, $host = '')
        {
            if (! touch(PLUGIN_ONLINE_USER_LIST)) return FALSE;

            // Open
            $fp = @fopen(PLUGIN_ONLINE_USER_LIST, 'r');
            if ($fp == FALSE) return FALSE;
            set_file_buffer($fp, 0);

            // Init
            $count   = 0;
            $found   = FALSE;
            $matches = array();

            flock($fp, LOCK_SH);

            // Read
            while (! feof($fp)) {
                $line = fgets($fp, 512);
                if ($line === FALSE) continue;

                // Ignore invalid-or-outdated lines
                if (! preg_match(PLUGIN_ONLINE_LIST_REGEX, $line, $matches) ||
                    ($matches[2] + PLUGIN_ONLINE_TIMEOUT) <= UTIME ||
                    $matches[2] > UTIME) continue;

                ++$count;
                if (! $found && $matches[1] == $host) $found = TRUE;
            }

            flock($fp, LOCK_UN);

            if(! fclose($fp)) return FALSE;

            if (! $found && $host != '') ++$count; // About you

            return $found;
        }

        // Cleanup outdated records, Add/Replace new record, Return the number of 'users in N seconds'
        // NOTE: Call this when plugin_online_check_online() returnes FALSE
        function plugin_online_sweep_records($host = '')
        {
            // Open
            $fp = @fopen(PLUGIN_ONLINE_USER_LIST, 'r+');
            if ($fp == FALSE) return FALSE;
            set_file_buffer($fp, 0);

            flock($fp, LOCK_EX);

            // Read to check
            $lines = @file(PLUGIN_ONLINE_USER_LIST);
            if ($lines === FALSE) $lines = array();

            // Need modify?
            $line_count = $count = count($lines);
            $matches = array();
            $dirty   = FALSE;
            for ($i = 0; $i < $line_count; $i++) {
                if (! preg_match(PLUGIN_ONLINE_LIST_REGEX, $lines[$i], $matches) ||
                    ($matches[2] + PLUGIN_ONLINE_TIMEOUT) <= UTIME ||
                    $matches[2] > UTIME ||
                    $matches[1] == $host) {
                    unset($lines[$i]); // Invalid or outdated or invalid date
                    --$count;
                    $dirty = TRUE;
                }
            }
            if ($host != '' ) {
                // Add new, at the top of the record
                array_unshift($lines, strtr($host, "\n", '') . '|' . UTIME . "\n");
                ++$count;
                $dirty = TRUE;
            }

            if ($dirty) {
                // Write
                if (! ftruncate($fp, 0)) return FALSE;
                rewind($fp);
                fputs($fp, join('', $lines));
            }

            flock($fp, LOCK_UN);

            if(! fclose($fp)) return FALSE;

            return $count; // Number of lines == Number of users online
        }

    //Syntax: {{onlinenumber|texts following the number of online (when the number is 1)|texts following the number of online (when the number is 2 or more)
    //The texts following the number of online are not required (If entered just {{onlinenumber}} , this will return only the number)

    function connectTo($mode) {
      $this->Lexer->addSpecialPattern('\{\{onlinenumber[^}]*\}\}',$mode,'plugin_onlinenumber');
    }


    function handle($match, $state, $pos, &$handler){

        static $count, $result, $base;

        return explode('|', substr($match, strlen('{{onlinenumber|'), -2));

    }

    function render($mode, &$renderer, $data) {
        define('PLUGIN_ONLINE_TIMEOUT', $this->getConf('onlineseconds')); // Count users in N seconds
        
        // List of 'IP-address|last-access-time(seconds)'
        define('PLUGIN_ONLINE_USER_LIST', DOKU_PLUGIN . 'onlinenumber/user.dat');

        // Regex of 'IP-address|last-access-time(seconds)'
        define('PLUGIN_ONLINE_LIST_REGEX', '/^([^\|]+)\|([0-9]+)$/');

        // UTIME ... universal time
        define('UTIME', time() - date('Z'));


        // Main process
        if (! isset($count)) {
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $host  = & $_SERVER['REMOTE_ADDR'];
            } else {
                $host  = '';
            }

            // Try read
            if ($this->plugin_online_check_online($count, $host)) {
                $result = TRUE;
            } else {
                // Write
                $result = $this->plugin_online_sweep_records($host);
            }
        }

        if ($result) { // Integer
            if ($count == 1) {
                $renderer->doc .= htmlspecialchars($count) .htmlspecialchars($data[0]);
            } else {
                $renderer->doc .= htmlspecialchars($count) .htmlspecialchars($data[1]);
            }
        } else {
            if (! isset($base)) $base = basename(PLUGIN_ONLINE_USER_LIST);
            $error = 'onlinenumber: "' . $base . '" not writable;';
            $renderer->doc .= htmlspecialchars($error); // String
        }

    }


}
 
?>