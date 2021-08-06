<?php

/**
 * constants.php
 * Important Console constants
 *
 * @author Lochemem Bruno Michael
 * @license Apache-2.0
 */

declare(strict_types=1);

namespace Chemem\Concurrently\Console;

/**
 * @var string HEADER
 */
const HEADER = <<<'HEAD'
  _____                                    __  __    
 / ___/__  ___  ______ _____________ ___  / /_/ /_ __
/ /__/ _ \/ _ \/ __/ // / __/ __/ -_) _ \/ __/ / // /
\___/\___/_//_/\__/\_,_/_/ /_/  \__/_//_/\__/_/\_, / 
                                              /___/  
HEAD;

/**
 * @var string VERSION
 */
const VERSION = 'v0.1.0';

/**
 * @var array HELP_INFO
 */
const HELP_INFO = [
  '-h, --help'          => 'Shows help',
  '-v, --version'       => 'Shows package version number',
  '-m, --max-processes' => 'Specifies the maximum number of processes to keep in queue',
  '--name-separator'    => 'Specifies the character to use to split process names',
  '--no-spinner'        => 'Prints output without the spinner',
  '--no-color'          => 'Disables colors from logging',
  '-s, --silent'        => 'Run processes silently; without logging any output',
];

/**
 * @var array EXAMPLES
 */
const EXAMPLES = [
  'Output nothing more than stdout + stderr'              => 'concurrently \"ls,cat file.txt\"',
  'Asynchronously print results to a file'                => 'concurrently \"ls,cat file.txt\" > log.txt',
  'Specify maximum number of processes to keep in queue'  => 'concurrently -m=4 \"ls,cat file.txt\"',
  'Specify process name separator'                        => 'concurrently --name-separator=\"|\" \"ls|cat file.txt\"',
  'Run tasks asynchronously without logging any output'   => 'concurrently -s \"composer server:run|yarn start\"',
  'Run tasks without the spinner loading effect'          => 'concurrently --no-spinner \"composer server:run, yarn start\"',
];

/**
 * @var array DEFAULT_PROC_OPTS
 */
const DEFAULT_PROC_OPTS = [
  'silent'          => false,
  'color'           => true,
  'spinner'         => true,
  'max_processes'   => null,
  'name_separator'  => ',',
  'processes'       => '',
];
