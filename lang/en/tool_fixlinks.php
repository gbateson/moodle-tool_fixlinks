<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'tool_fixlinks', language 'en', branch 'MOODLE_22_STABLE'
 *
 * @package    tool
 * @subpackage fixlinks
 * @copyright  2014 Gordon Bateson {@link http://quizport.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// essential strings
$string['pluginname'] = 'Fix files and URL links';
$string['pageheader'] = 'Fix files and URL links';

// more strings
$string['action'] = 'Action';
$string['action_help'] = 'This setting specifies what action is to be taken with files used by the selected activity modules in the the selected courses.

**Link files**
: Where possible files that have been copied to file areas in the selected activities will be converted to aliasses to files in the selected types of repository

**Unlink files**
: Any files that are aliasses to files in the selected types of repository will be converted to independent copies of the alias destination file.';
$string['actionlink'] = 'Link files';
$string['actionunlink'] = 'Unlink files';
$string['courses_help'] = 'Select one or more courses in which you wish to fix links in the selected types of resource and activity.';
$string['courses'] = 'Courses';
$string['coursesandactivitytypes'] = 'Courses and activity types';
$string['failed'] = 'Failed';
$string['fixnotyetavailable'] = 'fix not yet available';
$string['matchcontent_help'] = 'If this option is checked, then selected files will be linked to the first file with the same content found in any of the selected repositories, regardless of the file path.';
$string['matchcontent'] = 'Match file content';
$string['matchpath_help'] = 'If any conditions are specified on the path, then only files matching those conditions will be fixed. If no conditions are specified on the path, then all files will be fixed.

Additionally, you can specify prefixes to be removed and/or added to the paths of any files that are selected.';
$string['matchpath'] = 'Match file path';
$string['modnames_help'] = 'Select one or more types of activity whose files and URLs you wish to be fixed';
$string['modnames'] = 'Activity types';
$string['addpathprefix'] = 'Add prefix';
$string['removepathprefix'] = 'Remove prefix';
$string['repositorytypes_help'] = 'Select the types of repository you wish to be checked for possible destinations for file aliasses.';
$string['repositorytypes'] = 'Repositories';
$string['skipped'] = 'Skipped';
$string['updated'] = 'Updated';
$string['updating'] = 'Fixing files and URL links';

