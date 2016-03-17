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
 * Link or unlink files in activity areas with files in repositories
 *
 * @package    tool_fixlinks
 * @copyright  2013 onwards Gordon Bateson (http://github.com/gbateson)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once('../../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/lib/adminlib.php');

// for Moodle <=2.5, we must include the form file manually
require_once($CFG->dirroot.'/admin/tool/fixlinks/classes/form.php');

admin_externalpage_setup('toolfixlinks');

$form = new tool_fixlinks_form();

if ($form->is_cancelled()) {
    echo redirect(new moodle_url('/admin/index.php'));
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('pageheader', 'tool_fixlinks'));

if ($form->is_submitted() && $form->is_validated()) {
    echo $OUTPUT->box_start();
    $form->fix_links();
    echo $OUTPUT->box_end();
}

$form->display();

echo $OUTPUT->footer();
