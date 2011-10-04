<?php

/**
 * View and administrate BigBlueButton playback recordings
 *
 * Authors:
 *      Jesus Federico (jesus [at] b l i n ds i de n  e t w o r ks [dt] com)
 *
 * @package   mod_bigbluebutton
 * @copyright 2011 Blindside Networks Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */


defined('MOODLE_INTERNAL') || die();

$module->version   = 2011100400;      // The current module version (Date: YYYYMMDDXX)
$module->requires  = 2010080300;      // Requires this Moodle version
$module->cron      = 0;               // Period for cron to check this module (secs)
$module->component = 'mod_recordingsbn'; // To check on upgrade, that module sits in correct place