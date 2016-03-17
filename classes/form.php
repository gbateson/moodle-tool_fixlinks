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
 * admin/tool/fixlinks.php
 *
 * @package    tool
 * @subpackage fixlinks
 * @copyright  2014 Gordon Bateson {@link http://quizport.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/** Include required files */
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * tool_fixlinks_form
 *
 * @package    tool
 * @subpackage fixlinks
 * @copyright  2014 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */
class tool_fixlinks_form extends moodleform {

    const ACTION_LINK   = 1;
    const ACTION_UNLINK = 0;

    const OP_CONTAINS    = 'contains';
    const OP_DOES_NOT_CONTAIN = 'doesnotcontain';
    const OP_IS_EQUAL_TO = 'isequalto';
    const OP_STARTS_WITH = 'startswith';
    const OP_ENDS_WITH   = 'endswith';

    /** the names of modules that this tool can fix */
    public $modnames = array('assign',
                             'glossary',
                             'label',
                             'resource',
                             'quiz',
                             'hotpot',
                             'taskchain');

    /** time at which script execution is expected to end */
    protected $timeout = 0;

    /** counters for reporting on the fixing of links */
    protected $skipped = 0;
    protected $updated = 0;
    protected $failed  = 0;

    /** the types of repositories that this tool can access */
    public $repositorytypes = array('coursefiles', 'filesystem', 'user');

    /**
     * constructor
     */
    public function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {
        if (method_exists('moodleform', '__construct')) {
            parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
        } else {
            parent::moodleform($action, $customdata, $method, $target, $attributes, $editable);
        }
    }

    /**
     * definition
     */
    public function definition() {
        global $CFG, $DB, $USER;

        $mform = $this->_form;
        $tool = 'tool_fixlinks';
        $strman = get_string_manager();

        $fixnotyetavailable = get_string('fixnotyetavailable', $tool);
        $fixnotyetavailable = html_writer::tag('span', "($fixnotyetavailable)", array('class' => 'dimmed_text'));

        // ==================================
        // courses and activity types
        // ==================================
        //
        //$this->add_heading($mform, 'coursesandactivitytypes', $tool, true);

        // action (1=link, 0=unlink)
        $name = 'action';
        $label = get_string($name, $tool);
        $options = array(self::ACTION_LINK => get_string('actionlink', $tool),
                         self::ACTION_UNLINK => get_string('actionunlink', $tool));
        $mform->addElement('select', $name, $label, $options);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, self::ACTION_LINK);
        $mform->addHelpButton($name, $name, $tool);

        // courses
        $name = 'courses';
        $label = get_string($name, $tool);
        $options = $DB->get_records_select_menu('course', 'id <> ?', array(SITEID), 'shortname', 'id,shortname');
        $mform->addElement('select', $name, $label, $options, array('size' => min(5, count($options)),
                                                                    'multiple' => count($options) > 1));
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, '');
        $mform->addHelpButton($name, $name, $tool);

        // modnames
        $name = 'modnames';
        $label = get_string($name, $tool);
        $elements_name = 'elements_'.$name;

        $elements = array();
        foreach ($this->$name as $modname) {
            $text = get_string('pluginname', 'mod_'.$modname);
            if (! method_exists($this, 'fix_links_'.$modname)) {
                $elements[] = $mform->createElement('static', '', '', "$text $fixnotyetavailable");
            } else {
                $elements[] = $mform->createElement('checkbox', $name.'['.$modname.']', '', $text);
            }
        }

        $mform->addGroup($elements, $elements_name, $label, html_writer::empty_tag('br'), false);
        $mform->addHelpButton($elements_name, $name, $tool);

        $defaultvalue = ''; // $this->defaultvalue($name);
        $defaultvalue = explode(',', $defaultvalue);
        foreach ($this->$name as $modname => $text) {
            $mform->setType($name.'['.$modname.']', PARAM_INT);
            $mform->setDefault($name.'['.$modname.']', in_array($modname, $defaultvalue));
        }

        // repositorytypes
        $name = 'repositorytypes';
        $label = get_string($name, $tool);
        $elements_name = 'elements_'.$name;

        $elements = array();
        foreach ($this->$name as $type) {
            $text = 'repository_'.$type;
            if ($strman->string_exists('pluginname', $text)) {
                $text = get_string('pluginname', $text);
                $elements[] = $mform->createElement('checkbox', $name.'['.$type.']', '', $text);
            }
        }

        $mform->addGroup($elements, $elements_name, $label, html_writer::empty_tag('br'), false);
        $mform->addHelpButton($elements_name, $name, $tool);

        $defaultvalue = 'coursefiles,filesystem';
        $defaultvalue = explode(',', $defaultvalue);
        foreach ($this->$name as $type) {
            $mform->setType($name.'['.$type.']', PARAM_INT);
            $mform->setDefault($name.'['.$type.']', in_array($type, $defaultvalue));
        }

        // filter path
        $elements = array();
        $options = array(
            self::OP_CONTAINS    => get_string(self::OP_CONTAINS,    'filters'),
            self::OP_DOES_NOT_CONTAIN => get_string(self::OP_DOES_NOT_CONTAIN, 'filters'),
            self::OP_IS_EQUAL_TO => get_string(self::OP_IS_EQUAL_TO, 'filters'),
            self::OP_STARTS_WITH => get_string(self::OP_STARTS_WITH, 'filters'),
            self::OP_ENDS_WITH   => get_string(self::OP_ENDS_WITH,   'filters')
        );
        $elements[] = $mform->createElement('select', 'filterpathop', '', $options);
        $elements[] = $mform->createElement('static', '', '', ' ');
        $elements[] = $mform->createElement('text', 'filterpathtext', '', array('size' => 20));
        $elements[] = $mform->createElement('static', '', '', html_writer::empty_tag('br'));

        // remove path prefix
        $name = 'removepathprefix';
        $label = get_string($name, $tool);
        $elements[] = $mform->createElement('static', '', '', $label.' ');
        $elements[] = $mform->createElement('text', $name, '', array('size' => 20));
        $elements[] = $mform->createElement('static', '', '', html_writer::empty_tag('br'));

        // add path prefix
        $name = 'addpathprefix';
        $label = get_string($name, $tool);
        $elements[] = $mform->createElement('static', '', '', $label.' ');
        $elements[] = $mform->createElement('text', $name, '', array('size' => 20));
        $elements[] = $mform->createElement('static', '', '', html_writer::empty_tag('br'));

        // match path (group)
        $name = 'matchpath';
        $label = get_string($name, $tool);
        $elements_name = 'elements_'.$name;
        $mform->addGroup($elements, $elements_name, $label, '', false);
        $mform->addHelpButton($elements_name, $name, $tool);

        $mform->setDefault('filterpathop',  'startswith');

        $mform->setType('filterpathop',     PARAM_TEXT);
        $mform->setType('filterpathtext',   PARAM_TEXT);
        $mform->setType('removepathprefix', PARAM_TEXT);
        $mform->setType('addpathprefix',    PARAM_TEXT);

        // match content
        $name = 'matchcontent';
        $label = get_string($name, $tool);
        $mform->addElement('checkbox', $name, $label);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 0);
        $mform->addHelpButton($name, $name, $tool);

        // ==================================
        // action buttons
        // ==================================
        //
        $this->add_action_buttons(true, get_string('go'));

        // ==================================
        // javascript (if required)
        // ==================================
        //
        if (! method_exists($mform, 'setExpanded')) {
            // hide sections: names, defaults, display
            // include an external javascript file
            // to add show/hide buttons where needed
            $src = new moodle_url('/admin/tool/fixlinks/classes/form.js');
            $js = '<script type="text/javascript" src="'.$src.'"></script>';
            $mform->addElement('static', 'form_js', '', $js);
        }
    }

    /**
     * validation
     *
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        //$name = 'countusers';
        //if (empty($data[$name])) {
        //    $errors[$name] = get_string('required');
        //}

        return $errors;
    }

    /**
     * add_heading
     *
     * @param object $mform
     * @param string $name
     * @param string $plugin
     * @param boolean $expanded
     */
    public function add_heading($mform, $name, $plugin, $expanded) {
        $label = get_string($name, $plugin);
        $mform->addElement('header', $name, $label);
        if (method_exists($mform, 'setExpanded')) {
            $mform->setExpanded($name, $expanded);
        }
    }

    /**
     * fix_links
     */
    public function fix_links() {
        global $DB, $USER;

        // get form data
        $data = $this->get_data();
        $time = time();

        $action = $data->action;

        $courses = array();
        if (isset($data->courses)) {
            if (is_array($data->courses)) {
                foreach ($data->courses as $course) {
                    $courses[] = $course;
                }
            } else {
                $courses[] = $data->courses;
            }
            $courses = array_filter($courses);
        }

        $modnames = array();
        if (isset($data->modnames)) {
            foreach ($data->modnames as $modname => $selected) {
                if ($selected) {
                    if (in_array($modname, $this->modnames)) {
                        $modnames[] = $modname;
                    }
                }
            }
        }

        $repositorytypes = array();
        if (isset($data->repositorytypes)) {
            foreach ($data->repositorytypes as $type => $selected) {
                if ($selected) {
                    if (in_array($type, $this->repositorytypes)) {
                        $id = $DB->get_field('repository', 'id', array('type' => $type));
                        $repositorytypes[$id] = $type;
                    }
                }
            }
        }

        // initialize counters
        $this->skipped = 0;
        $this->updated = 0;
        $this->failed  = 0;

        $i_max = 0;
        $rs = false;
        if (count($courses) && count($modnames)) {

            $where = array();
            $params = array();
            list($where[0], $params[0]) = $DB->get_in_or_equal($courses);
            list($where[1], $params[1]) = $DB->get_in_or_equal($modnames);

            $select = 'cm.id, cm.course, cm.module, '.
                      'm.name AS modulename, m.id AS moduleid';
            $from   = '{course_modules} cm '.
                      'JOIN {modules} m ON cm.module = m.id';
            $where  = 'cm.course '.$where[0].' AND m.name '.$where[1];
            $order  = 'cm.course, cm.module, cm.id';
            $params = array_merge($params[0], $params[1]);

            if ($i_max = $DB->count_records_sql("SELECT COUNT(*) FROM $from WHERE $where", $params)) {
                $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params);
            }
            unset($select, $from, $where, $params);
        }

        if ($rs) {
            $i = 0; // record counter

            if (defined('STDIN') && defined('CLI_SCRIPT')) {
                $bar = false;
            } else {
                $bar = new progress_bar('fixlinks', 500, true);
            }

            $tool = 'tool_fixlinks';
            $str = (object)array(
                'updating' => get_string('updating', $tool),
                'skipped'  => get_string('skipped',  $tool),
                'updated'  => get_string('updated',  $tool),
                'failed'   => get_string('failed',   $tool)
            );

            // loop through answer records
            $course = null;
            foreach ($rs as $cm) {
                $i++; // increment record count

                // apply for more script execution time (3 mins)
                $this->set_timeout();

                // get settings for this course, if necessary
                if ($course && $course->id==$cm->course) {
                    // same course - do nothing
                } else {
                    $course = $DB->get_record('course', array('id' => $cm->course));
                    $course->context = self::get_context(CONTEXT_COURSE, $cm->course);
                    $course->legacyfiles = null;
                }

                // get context for this $cm record
                $cm->context = self::get_context(CONTEXT_MODULE, $cm->id);

                $method = 'fix_links_'.$cm->modulename;
                if (method_exists($this, $method)) {
                    $this->$method($data, $repositorytypes, $course, $cm);
                }

                // update progress bar
                if ($bar) {
                    $msg = array();
                    if ($i) {
                        $msg[] = "$i/$i_max";
                    }
                    if ($this->skipped) {
                        $msg[] = $str->skipped.': '.$this->skipped;
                    }
                    if ($this->updated) {
                        $msg[] = $str->updated.': '.$this->updated;
                    }
                    if ($this->failed) {
                        $msg[] = $str->failed.': '.$this->failed;
                    }
                    $bar->update($i, $i_max, $str->updating.' ('.implode(', ', $msg).')');
                }
            }
            $rs->close();
        }
    }

    /**
     * set_timeout
     *
     * @param integer $max_execution_time in seconds (optional, default = 300)
     */
    protected function set_timeout($max_execution_time=300) {
        $time = time();
        if ($this->timeout <= $time) {
            if ($max_execution_time < 60) {
                $max_execution_time = 60;
            }
            if (class_exists('core_php_time_limit')) {
                // Moodle >= 2.7
                if (CLI_SCRIPT) {
                    core_php_time_limit::raise();
                } else {
                    core_php_time_limit::raise($max_execution_time);
                }
            } else {
                // Moodle <= 2.6
                if (CLI_SCRIPT) {
                    set_time_limit(0);
                } else {
                    set_time_limit($max_execution_time);
                }
            }
            $this->timeout = ($time + $max_execution_time);
        }
    }

    /**
     * fix_links_hotpot
     *
     * @param object $data
     * @param array  $repositorytypes
     * @param object $course
     * @param object $cm
     * @return void
     * @todo Finish documenting this function
     */
    protected function fix_links_hotpot($data, $repositorytypes, $course, $cm) {
        $fileareas = array('sourcefile', 'configfile');
        $this->fix_fileareas($data, $repositorytypes, $course, $cm, 'mod_hotpot', $fileareas);
    }

    /**
     * fix_links_taskchain
     *
     * @param object $data
     * @param array  $repositorytypes
     * @param object $course
     * @param object $cm
     * @return void
     * @todo Finish documenting this function
     */
    protected function fix_links_taskchain($data, $repositorytypes, $course, $cm) {
        $fileareas = array('sourcefile', 'configfile');
        $this->fix_fileareas($data, $repositorytypes, $course, $cm, 'mod_taskchain', $fileareas);
    }

    /**
     * fix_fileareas
     *
     * @param integer  $action
     * @param array    $repositorytypes
     * @param object   $course
     * @param object   $cm
     * @param string   $component
     * @param string   $filearea
     * @return void
     * @todo Finish documenting this function
     */
    protected function fix_fileareas($data, $repositorytypes, $course, $cm, $component, $fileareas) {
        global $DB, $USER;

        if (in_array('filesystem', $repositorytypes)) {
            $params = array($course->context, self::get_context(CONTEXT_SYSTEM));
            $params = array('type'=> 'filesystem', 'context' => $params);
            $instances = repository::get_instances($params);
        }

        if (in_array('user', $repositorytypes)) {
            $USER->context = self::get_context(CONTEXT_USER, $USER->id);
        }

        $fs = get_file_storage();
        $itemid = 0;
        $rootpath = '/';
        $recursive = true;
        $contextid = $cm->context->id;
        foreach ($fileareas as $filearea) {

            $files = $fs->get_directory_files($contextid, $component, $filearea, $itemid, $rootpath, $recursive);
            foreach ($files as $file) {

                if ($file->is_directory()) {
                    continue;
                }

                $filepath = $file->get_filepath();
                $filename = $file->get_filename();
                $sortorder = $file->get_sortorder();

                // filter path (if necessary)
                $path = "$filepath$filename";
                if (! $this->filter_path($data, $path)) {
                    continue;
                }

                // (sortorder=1 siginifies the "mainfile" in this filearea)

                // $file_record for this $file
                $filerecord = array(
                    'contextid' => $contextid, 'component' => $component, 'filearea' => $filearea,
                    'sortorder' => $sortorder, 'itemid' => $itemid, 'filepath' => $filepath, 'filename' => $filename
                );

                $contenthash = $file->get_contenthash();
                $referencefileid = $file->get_referencefileid();

                if ($data->action==self::ACTION_LINK) {

                    // check file is not already an alias
                    if ($referencefileid) {
                        $this->skipped++;
                        continue;
                    }

                    // remove/add path prefix
                    $path = $this->fix_path($data, $path);

                    $filepath = dirname($path);
                    $filepath = rtrim($filepath, '/').'/';
                    $filename = basename($path);
                    $filename = ltrim($filename, '/');

                    // locate file in repository
                    foreach ($repositorytypes as $repositoryid => $repositorytype) {

                        switch ($repositorytype) {
                            case 'coursefiles':
                                $sourcefile = $this->locate_sourcefile_in_coursefiles($course, $filepath, $filename, $contenthash);
                                break;
                            case 'filesystem':
                                $sourcefile = $this->locate_sourcefile_in_filesystem($instances, $filepath, $filename, $contenthash);
                                break;
                            case 'user':
                                $sourcefile = $this->locate_sourcefile_in_user($USER, $filepath, $filename, $contenthash);
                                break;
                            default:
                                $sourcefile = null;
                        }

                        // convert file to alias (if possible)
                        if ($sourcefile) {

                            // remove the hard copy file from this $filearea
                            $file->delete();

                            // create alias to source file
                            if ($fs->create_file_from_reference($filerecord, $repositoryid, $sourcefile)) {
                                $this->updated++;
                                break; // skip other repositorytypes
                            } else {
                                $this->failed++;
                            }
                        }
                    }

                } else if ($data->action==self::ACTION_UNLINK) {

                    // get check this file is an alias
                    if (! $referencefileid) {
                        $this->skipped++;
                        continue;
                    }
                    if (! $repositoryid = $file->get_repository_id()) {
                        $this->skipped++;
                        continue; // $file is not from an external repository
                    }

                    // get repository of alias target
                    if (! $repository = repository::get_repository_by_id($repositoryid, $cm->context)) {
                        $this->skipped++;
                        continue; // $repository is not accessible in this context - shouldn't happen !!
                    }

                    // get repository type
                    switch (true) {
                        case isset($repository->options['type']):
                            $type = $repository->options['type'];
                            break;
                        case isset($repository->instance->typeid):
                            $type = repository::get_type_by_id($repository->instance->typeid);
                            $type = $type->get_typename();
                            break;
                        default:
                            $type = ''; // shouldn't happen !!
                    }

                    $sourcefile = $file->get_reference();
                    switch ($type) {

                        case 'filesystem':
                            $create_file = 'create_file_from_pathname';
                            if (method_exists($repository, 'get_rootpath')) {
                                $sourcefile = $repository->get_rootpath().$sourcefile;
                            } else {
                                $sourcefile = $repository->root_path.$sourcefile;
                            }
                            break;

                        case 'coursefiles':
                        case 'user':
                            $create_file = 'create_file_from_storedfile';
                            $sourcefile = $repository->get_moodle_file($sourcefile);
                            break;

                        default:
                            $create_file = ''; // shouldn't happen !!
                    }

                    // convert link to a hard copy of source file
                    if ($create_file) {

                        // remove the current file, which is an alias to a file in a repository
                        $file->delete();

                        // add a hard copy of the source file
                        if ($fs->$create_file($filerecord, $sourcefile)) {
                            $this->updated++;
                        } else {
                            $this->failed++;
                        }
                    } else {
                        $this->skipped++;
                    }
                }
            }
        }
    }

    /**
     * filter_path
     *
     * @param object $data
     * @param string $path
     * @return void
     * @todo Finish documenting this function
     */
    protected function filter_path($data, $path) {
        if ($data->filterpathtext=='') {
            return true;
        }
        if ($data->filterpathop==self::OP_IS_EQUAL_TO) {
            return ($data->filterpathtext==$path);
        }
        $text = preg_quote($data->filterpathtext, '/');
        switch ($data->filterpathop) {
            case self::OP_CONTAINS:    return preg_match('/'.$text.'/', $path);
            case self::OP_DOES_NOT_CONTAIN: return (! preg_match('/'.$text.'/', $path));
            case self::OP_STARTS_WITH: return preg_match('/^'.$text.'/',  $path);
            case self::OP_ENDS_WITH:   return preg_match('/'.$text.'$/',  $path);
            default: return false;
        }
    }

    /**
     * fix_path
     *
     * @param object $data
     * @param string $path
     * @return void
     * @todo Finish documenting this function
     */
    protected function fix_path($data, $path) {
        if ($prefix = $data->removepathprefix) {
            $strlen = strlen($prefix);
            if (substr($path, 0, $strlen)==$prefix) {
                $path = substr($path, $strlen);
            }
        }
        if ($prefix = $data->addpathprefix) {
            $path = $prefix.$path;
        }
        return $path;
    }

    /**
     * locate_sourcefile_in_coursefiles
     *
     * @param object  $course
     * @param string  $filepath
     * @param string  $filename
     * @return array(object $file, integer repositoryid)
     * @todo Finish documenting this function
     */
    protected function locate_sourcefile_in_coursefiles($course, $filepath, $filename, $contenthash) {
        return $this->locate_file($course->context->id, 'course', 'legacy', 0, $filepath, $filename, $contenthash);
    }

    /**
     * locate_sourcefile_in_filesystem
     *
     * @param object  $course
     * @param string  $filepath
     * @param string  $filename
     * @return object or false
     * @todo Finish documenting this function
     */
    protected function locate_sourcefile_in_filesystem($instances, $filepath, $filename, $contenthash) {
        $source = ltrim($filepath, '/').$filename;
        foreach ($instances as $instance) {
            if ($listing = $instance->get_listing($filepath)) {
                foreach ($listing['list'] as $file) {
                    if (isset($file['source']) && $file['source']==$source) {
                        return $source;
                    }
                }
            }
        }
        return false;
    }

    /**
     * locate_sourcefile_in_user
     *
     * @param object  $course
     * @param string  $filepath
     * @param string  $filename
     * @return object or false
     * @todo Finish documenting this function
     */
    protected function locate_sourcefile_in_user($user, $filepath, $filename, $contenthash) {
        return $this->locate_file($user->context->id, 'user', 'private', 0, $filepath, $filename, $contenthash);
    }

    /**
     * locate_file
     *
     * @param integer $contextid
     * @param string  $component
     * @param string  $filearea
     * @param integer $itemid
     * @param string  $filepath
     * @param string  $filename
     * @return object or false
     * @todo Finish documenting this function
     */
    protected function locate_file($contextid, $component, $filearea, $itemid, $filepath, $filename, $contenthash) {
        $fs = get_file_storage();
        // $fs->content_exists($contenthash)==$contenthash
        if ($fs->file_exists($contextid, $component, $filearea, $itemid, $filepath, $filename)) {
            return file_storage::pack_reference(array(
                'contextid' => $contextid,
                'component' => $component,
                'filearea'  => $filearea,
                'itemid'    => $itemid,
                'filepath'  => $filepath,
                'filename'  => $filename
            ));
        } else {
            return false; // file not found
        }
    }

    /**
     * get_context
     *
     * a wrapper method to offer consistent API to get contexts
     * in Moodle 2.0 and 2.1, we use context() function
     * in Moodle >= 2.2, we use static context_xxx::instance() method
     *
     * @param integer $contextlevel
     * @param integer $instanceid (optional, default=0)
     * @param int $strictness (optional, default=0 i.e. IGNORE_MISSING)
     * @return required context
     * @todo Finish documenting this function
     */
    public static function get_context($contextlevel, $instanceid=0, $strictness=0) {
        if (class_exists('context_helper')) {
            // use call_user_func() to prevent syntax error in PHP 5.2.x
            // return $classname::instance($instanceid, $strictness);
            $class = context_helper::get_class_for_level($contextlevel);
            return call_user_func(array($class, 'instance'), $instanceid, $strictness);
        } else {
            return get_context_instance($contextlevel, $instanceid);
        }
    }
}
