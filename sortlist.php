<?php
// This file is part of mod_grouptool for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// If not, see <http://www.gnu.org/licenses/>.

/**
 * sortlist.php
 * Defines the version of grouptool
 *
 * @package       mod_grouptool
 * @author        Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author        Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author        Philipp Hager
 * @copyright     2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->libdir . '/pear/HTML/QuickForm/element.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Class for a sortable list of (de-)activateable elements, with the ability to define
 * additional data for each element.
 *
 * @package MoodleQuickForm_sortlist
 */
class MoodleQuickForm_sortlist extends HTML_QuickForm_element {

    /**
     * Label of the field
     * @var       string
     * @since     1.3
     * @access    private
     */
    public $_label = '';

    /**
     * Name of the Element
     * @var       string
     * @since     1.3
     * @access    private
     */
    public $_name = '';

    /**
     * Form element type
     * @var       string
     * @since     1.0
     * @access    private
     */
    public $_type = '';

    /**
     * Flag to tell if element is frozen
     * @var       boolean
     * @since     1.0
     * @access    private
     */
    public $_flagfrozen = false;

    /**
     * Does the element support persistant data when frozen
     * @var       boolean
     * @since     1.3
     * @access    private
     */
    public $_persistantfreeze = false;

    /**
     * Options for the element
     * ['classes'] is an array containing all the classes names for which selector/deselector-links
     * should be displayed
     *
     */
    public $_options = array('classes' => array(), 'add_fields' => array(), 'all_string' => 'All', 'curactive' => array());

    /**
     * value
     * $_value[]['name']
     * $_value[]['active']
     * $_value[]['curactive']
     * $_value[]['sort_order']
     * $_value[]['classes']
     * + additional data
     */
    public $_value = array();

    /**
     * These complement separators, they are appended to the resultant HTML
     * @access   private
     * @var      array
     */
    public $_wrap = array('', '');

    /**
     * Class constructor
     *
     * @access   public
     * @param    string  Element's name
     * @param    object  groupdata
     * @param    array   Options to control the element's display
     * @param    mixed   Either a typical HTML attribute string or an associative array
     */
    public function __construct($elementname = null, $options = array(), $attributes = null) {
        $this->HTML_QuickForm_element($elementname, '', $attributes);
        $this->_persistantfreeze = true;
        $this->_appendName = true;
        $this->_type = 'sortlist';
        // Set the options, do not bother setting bogus ones!
        if (is_array($options)) {
            foreach ($options as $name => $value) {
                if (isset($this->_options[$name])) {
                    if (is_array($value) && is_array($this->_options[$name])) {
                        $this->_options[$name] = $this->_options[$name]+$value;
                    } else {
                        $this->_options[$name] = $value;
                    }
                }
            }
        }
    }

    /**
     * Returns the current API version
     *
     * @since     1.0
     * @access    public
     * @return    float
     */
    public function apiVersion() {
        return 2.0;
    }

    /**
     * Returns element type
     *
     * @since     1.0
     * @access    public
     * @return    string
     */
    public function getType() {
        return $this->_type;
    }

    /**
     * Sets the input field name
     *
     * @param     string    $name   Input field name attribute
     * @since     1.0
     * @access    public
     * @return    void
     */
    public function setName($name) {
        $this->_name = $name;
    }

    /**
     * Returns the element name
     *
     *
     * @since     1.0
     * @access    public
     * @return    string
     */
    public function getName() {
        return $this->_name;
    }

    /**
     * swaps 2 list-elements
     *
     * @param    int    $a    first Element to swap
     * @param    int    $b    the other Element to swap with
     */
    public function _swapElements($a, $b) {
        if (isset($this->_value[$a]) && isset($this->_value[$b])) {
            $temp = $this->_value[$a]['sort_order'];
            $this->_value[$a]['sort_order'] = $this->_value[$b]['sort_order'];
            $this->_value[$b]['sort_order'] = $temp;
            // Reorder Elements!
            uasort($this->_value, array(&$this, "cmp"));
        } else {
            print_error('Item swap not possible, 1 of the Elements doesn\'t exist!');
        }
    }

    /**
     * moves an Element 1 step up
     *
     * @param    int    $index    Element to move
     */
    public function _move1up($index) {
        reset($this->_value);
        while (key($this->_value) != $index) {
            next($this->_value);
        }
        prev($this->_value);
        $otherindex = key($this->_value);
        $this->_swapElements($index, $otherindex);
    }

    /**
     * moves an Element 1 step down
     *
     * @param    int    $index    Element to move
     */
    public function _move1down($index) {
        reset($this->_value);
        while (key($this->_value) != $index) {
            next($this->_value);
        }
        next($this->_value);
        $otherindex = key($this->_value);
        $this->_swapElements($index, $otherindex);
    }

    /**
     * Sets the value of the form element
     *
     * @param     array    $value      Default value of the form element
     * @since     1.0
     * @access    public
     * @return    void
     */
    public function setValue($value) {
        $this->_value = $value;
        if (is_array($value) && ($value != null)) {
            uasort($this->_value, array(&$this, "cmp"));
        }
    }

    /**
     * compares if two groups are in correct order
     */
    public function cmp($element1, $element2) {
        if ($element1['sort_order'] == $element2['sort_order']) {
            return 0;
        } else {
            return $element1['sort_order'] > $element2['sort_order'] ? 1 : -1;
        }
    }

    /**
     * Returns the value of the form element
     *
     * @since     1.0
     * @access    public
     * @return    mixed
     */
    public function getValue($sorted=true) {
        $this->_refresh_element_order();
        $this->_refresh_select_state();
        if (!$sorted) {
            return $this->_clean_addfields($this->_value);
        }
        $elementdata = $this->_clean_addfields($this->_value);
        uasort($elementdata, array(&$this, "cmp"));
        return $elementdata;
    }

    /**
     * Freeze the element so that only its value is returned
     *
     * @access    public
     * @return    void
     */
    public function freeze() {
        $this->_flagfrozen = true;
    }

    /**
     * Unfreezes the element so that it becomes editable
     *
     * @access public
     * @return void
     * @since  3.2.4
     */
    public function unfreeze() {
        $this->_flagfrozen = false;
    }

    /**
     * Returns the value of field without HTML tags
     *
     * @since     1.0
     * @access    public
     * @return    string
     */
    public function getFrozenHtml() {
        return $this->_getPersistantData();
    }

    /**
     * Used by getFrozenHtml() to pass the element's value if _persistantfreeze is on
     *
     * @access private
     * @return string
     */
    public function _getPersistantData() {
        global $CFG, $PAGE, $OUTPUT, $DB;
        if (!$this->_persistantfreeze) {
            return '';
        } else {
            // Generate draggable items - each representing 1 group!
            $dragableitems = "";
            $name = $this->getName(true);
            $showmembersstr = get_string('show_members', 'grouptool');
            $moveupstr = get_string('moveup', 'grouptool');
            $movedownstr = get_string('movedown', 'grouptool');
            $groupdata = $this->getValue();
            $firstkey = key($groupdata);
            end($groupdata);
            $lastkey = key($groupdata);
            reset($groupdata);
            $table = new html_table();
            $table->data = array();
            foreach ($groupdata as $id => $group) {
                $row = array(); // Each group gets its own row!

                $namebase = $name.'['.$id.']';

                if (!key_exists('classes', $group)) {
                    $group['classes'] = array();
                } else {
                    if (!is_array($group['classes'])) {
                        $group['classes'] = array();
                    }
                }
                $chkboxattr = array(
                        'name'     => $namebase.'[active]',
                        'type'     => 'checkbox',
                        'disabled' => 'disabled',
                        'value'    => $group['active']);
                if ($group['active']) {
                    $chkboxattr['checked'] = 'checked';
                } else if (isset($chkboxattr['checked'])) {
                    unset($chkboxattr['checked']);
                }
                $advchkboxattr = $chkboxattr;
                $advchkboxattr['value'] = $group['active'];
                $advchkboxattr['type'] = 'hidden';
                $hiddenattr = array('name'  => $namebase.'[sort_order]',
                                    'type'  => 'hidden',
                                    'value' => $group['sort_order'],
                                    'class' => 'sort_order');
                $classelements = '';
                foreach($group['classes'] as $class) {
                    $classesattr = array(
                            'name'  => $namebase.'[classes][]',
                            'type'  => 'hidden',
                            'value' => $class,
                            'class' => 'hidden_classes');
                    $classelements .= html_writer::empty_tag('input', $classesattr);
                }
                $moveupattr = array('src'      => $OUTPUT->pix_url('i/up'),
                                    'alt'      => $moveupstr,
                                    'type'     => 'image',
                                    'name'     => 'moveup['.$id.']',
                                    'disabled' => 'disabled',
                                    'class'    => 'moveupbutton');
                $this->_noSubmitButtons[] = 'moveup['.$id.']';
                if ($id == $firstkey) {
                    $moveupattr['style'] = "visibility:hidden;";
                }
                $moveupbutton = html_writer::empty_tag('input', $moveupattr);
                $movedownattr = array('src'      => $OUTPUT->pix_url('i/down'),
                                      'alt'      => $movedownstr,
                                      'type'     => 'image',
                                      'name'     => 'movedown['.$id.']',
                                      'disabled' => 'disabled',
                                      'class'    => 'movedownbutton');
                $this->_noSubmitButtons[] = 'movedown['.$id.']';
                if ($id == $lastkey) {
                    $movedownattr['style'] = "visibility:hidden;";
                }
                $movedownbutton = html_writer::empty_tag('input', $movedownattr);

                // We don't need a drag button here, all is disabled...

                $nameattr = array('name'  => $namebase.'[name]',
                                  'type'  => 'hidden',
                                  'value' => $group['name']);
                $nameblock = html_writer::tag('span', $group['name'], array('class'=>'text')).html_writer::empty_tag('input', $nameattr);

                $row = array( 0 => new html_table_cell(html_writer::empty_tag('input', $advchkboxattr).
                                                       html_writer::empty_tag('input', $chkboxattr)),
                              1 => new html_table_cell($nameblock.
                                                       html_writer::empty_tag('input', $hiddenattr).
                                                       $classelements));
                $row[0]->attributes['class'] = 'checkbox_container';
                $row[1]->attributes['class'] = 'grpname';
                $additionalfields = "";
                foreach ($this->_options['add_fields'] as $key => $fielddata) {
                    if (!isset($group[$fielddata->name]) || $group[$fielddata->name] == null) {
                        $group[$fielddata->name] = $fielddata->stdvalue;
                    }
                    $attr = array(
                            'name'     => $namebase."[$fielddata->name]",
                            'type'     => $fielddata->type,
                            'disabled' => 'disabled',
                            'value'    => $group[$fielddata->name]);
                    $attr = array_merge($attr, $fielddata->attr);
                    if (!empty($fielddata->label)) {
                        $labelhtml = html_writer::tag('label', $fielddata->label,
                                                      array('for' => $attr['id']));
                    } else {
                        $labelhtml = "";
                    }
                    $currentfield = $labelhtml.html_writer::empty_tag('input', $attr);
                    $additionalfields[] = $currentfield;

                    $labelcell = new html_table_cell($labelhtml);
                    $labelcell->attributes['class'] = $fielddata->name." addfield";

                    $fieldcell = new html_table_cell(html_writer::empty_tag('input', $attr));
                    $fieldcell->attributes['class'] = $fielddata->name." addfield";
                    $row[] = $labelcell;
                    $row[] = $fieldcell;
                }

                $moveup = new html_table_cell($moveupbutton);
                $moveup->attributes['class'] = 'buttons';
                $row[] = $moveup;
                $movedown = new html_table_cell($movedownbutton);
                $movedown->attributes['class'] = 'buttons';
                $row[] = $movedown;

                $row = new html_table_row($row);
                $row->attributes['class'] = 'draggable_item';
                if ($this->_options['curactive'][$id]) {
                    $row->attributes['class'] .= ' current';
                }

                $rows[] = $row;
            }

            $table->data = $rows;
            $table->attributes['class'] .= 'drag_list table table-condensed';
            $tablehtml = html_writer::table($table);

            // We have no use for checkboxcontrol and JS here!

            $content = html_writer::tag('div', $tablehtml, array('class' => 'drag_area'));

            $html = html_writer::tag('div', $content, array('class' => 'fitem sortlist_container'));

            return $html;
        }
    }

    /**
     * Returns whether or not the element is frozen
     *
     * @since     1.3
     * @access    public
     * @return    bool
     */
    public function isFrozen() {
        return $this->_flagfrozen;
    }

    /**
     * Sets wether an element value should be kept in an hidden field
     * when the element is frozen or not
     *
     * @param     bool    $persistant   True if persistant value
     * @since     2.0
     * @access    public
     * @return    void
     */
    public function setPersistantFreeze($persistant=false) {
        $this->_persistantfreeze = $persistant;
    }

    /**
     * Sets display text for the element
     *
     * @param     string    $label  Display text for the element
     * @since     1.3
     * @access    public
     * @return    void
     */
    public function setLabel($label) {
        $this->_label = $label;
    }

    /**
     * Returns display text for the element
     *
     * @since     1.3
     * @access    public
     * @return    string
     */
    public function getLabel() {
        return $this->_label;
    }

    /**
     * Tries to find the element value from the values array
     *
     * @since     2.7
     * @access    private
     * @return    mixed
     */
    public function _findValue(&$values, $elementname = null) {
        if (empty($values)) {
            return null;
        }
        if ($elementname == null) {
            $elementname = $this->getName();
        }
        if (isset($values[$elementname])) {
            return $values[$elementname];
        } else if (strpos($elementname, '[')) {
            $myvar = "['" . str_replace(array(']', '['), array('', "']['"), $elementname) . "']";
            return eval("return (isset(\$values$myvar)) ? \$values$myvar : null;");
        } else {
            return null;
        }
    }

    private function _clean_addfields($data) {
        if (!is_array($data)) {
            return $data;
        }
        foreach ($data as $id => $group) {
            foreach ($this->_options['add_fields'] as $key => $fielddata) {
                if (empty($fielddata->param_type)) {
                    $fielddata->param_type = PARAM_TEXT;
                }
                if (isset($data[$id][$fielddata->name])) {
                    $data[$id][$fielddata->name] = clean_param($group[$fielddata->name], $fielddata->param_type);
                }
            }
        }

        return $data;
    }

    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     *
     * @param     string    $event  Name of event
     * @param     mixed     $arg    event arguments
     * @param     object    $caller calling object
     * @since     1.0
     * @access    public
     * @return    void
     */
    public function onQuickFormEvent($event, $arg, &$caller) {
        switch ($event) {
            case 'createElement':
                $className = get_class($this);
                $this->__construct($arg[0], $arg[1], $arg[3]);
                break;
            case 'addElement':
                $this->onQuickFormEvent('createElement', $arg, $caller);
                $this->onQuickFormEvent('updateValue', null, $caller);
                break;
            case 'updateValue':
                /*
                 * constant values override both default and submitted ones
                 * default values are overriden by submitted
                 */
                $value = $this->_clean_addfields($this->_findValue($caller->_constantValues));
                if (null === $value) {
                    $value = $this->_clean_addfields($this->_findValue($caller->_submitValues));
                    /*
                     * let the form-handling-php belief there was no submission
                     * if it was just a moveup/movedown submit
                     */
                    if (optional_param_array('moveup', 0, PARAM_INT)
                            || optional_param_array('movedown', 0, PARAM_INT)) {
                        $caller->_flagSubmitted = false;
                    }
                    // Same for the checkbox-controller buttons!
                    if (optional_param('do_class_action', 0, PARAM_INT)) {
                        $caller->_flagSubmitted = false;
                    }
                    if (null === $value) {
                        $value = $this->_clean_addfields($this->_findValue($caller->_defaultValues));
                    }
                }
                if (null !== $value) {
                    $this->setValue($value);
                }
                break;
        }
        return true;
    }

    /**
     * Accepts a renderer
     *
     * @param object     An HTML_QuickForm_Renderer object
     * @param bool       Whether an element is required
     * @param string     An error message associated with an element
     * @access public
     * @return void
     */
    public function accept(&$renderer, $required=false, $error=null) {
        $renderer->_templates[$this->getName()] = '<div class="qfelement<!-- BEGIN error --> error<!-- END error -->">'.
                                                  '<!-- BEGIN error --><span class="error">{error}</span><br />'.
                                                  '<!-- END error -->{element}</div>';
        $renderer->renderElement($this, $required, $error);
    }

    /**
     * Automatically generates and assigns an 'id' attribute for the element.
     *
     * Currently used to ensure that labels work on radio buttons and
     * checkboxes. Per idea of Alexander Radivanovich.
     *
     * @access private
     * @return void
     */
    public function _generateId() {
        if ($this->getAttribute('id')) {
            return;
        }

        $id = $this->getName();
        $id = 'id_' . str_replace(array('qf_', '[', ']'), array('', '_', ''), $id);
        $id = clean_param($id, PARAM_ALPHANUMEXT);
        $this->updateAttributes(array('id' => $id));
    }

    /**
     * Returns a 'safe' element's value
     *
     * @param  array   array of submitted values to search
     * @param  bool    whether to return the value as associative array
     * @access public
     * @return mixed
     */
    public function exportValue(&$submitValues, $assoc = false) {
        $value = $this->_findValue($submitValues);
        if (null === $value) {
            $value = $this->getValue();
        }
        $value = $this->_clean_addfields($value);
        return $this->_prepareValue($value, $assoc);
    }

    /**
     * Used by exportValue() to prepare the value for returning
     *
     * @param  mixed   the value found in exportValue()
     * @param  bool    whether to return the value as associative array
     * @access private
     * @return mixed
     */
    public function _prepareValue($value, $assoc) {
        if (null === $value) {
            return null;
        } else if (!$assoc) {
            return $value;
        } else {
            $name = $this->getName();
            if (!strpos($name, '[')) {
                return array($name => $value);
            } else {
                $valueAry = array();
                $myIndex  = "['" . str_replace(array(']', '['), array('', "']['"), $name) . "']";
                eval("\$valueAry$myIndex = \$value;");
                return $valueAry;
            }
        }
    }

    /**
     * Sets the element type
     *
     * @param     string    $type   Element type
     * @since     1.0
     * @access    public
     * @return    void
     */
    public function setType($type) {
        $this->_type = $type;
        $this->updateAttributes(array('type' => $type));
    }

    /**
     * updates the element selected-state if corresponding params are set
     */
    public function _refresh_select_state() {
        global $COURSE;
        $classes = optional_param_array('classes', array(0), PARAM_INT);
        $action = optional_param('class_action', 0, PARAM_ALPHA);
        $go_button = optional_param('do_class_action', 0, PARAM_BOOL);

        if (empty($go_button)) {
            return;
        }

        if ( $classes == null || count($classes) == 0 ) {
            $this->_flagSubmitted = false;
            return;
        }

        if (!empty($action)) {
            $keys = array();

            $groups = array();
            foreach ($classes as $groupingid) {
                $groups = array_merge($groups, groups_get_all_groups($COURSE->id, 0, $groupingid));
            }

            foreach ($groups as $current) {
                switch($action) {
                    case 'select':
                        $this->_value[$current->id]['selected'] = 1;
                        break;
                    case 'deselect':
                        $this->_value[$current->id]['selected'] = 0;
                        break;
                    case 'toggle':
                        $next = !$this->_value[$current->id]['selected'];
                        $this->_value[$current->id]['selected'] = $next;
                        break;
                }
            }
        }

        $this->_flagSubmitted = false;
    }

    /**
     * refreshs the element order via move1up() and move1down() if corresponding params are set
     */
    public function _refresh_element_order() {

        $moveup = optional_param_array('moveup', null, PARAM_INT);
        $movedown = optional_param_array('movedown', null, PARAM_INT);

        if ($moveup != null) {
            uasort($this->_value, array(&$this, "cmp"));
            $moveup = array_keys($moveup);
            $this->_move1up($moveup[0]);
            $this->_flagSubmitted = false;
        }

        if ($movedown != null) {
            uasort($this->_value, array(&$this, "cmp"));
            $movedown = array_keys($movedown);
            $this->_move1down($movedown[0]);
            $this->_flagSubmitted = false;
        }
    }

    /**
     * Returns the input field in HTML
     *
     * @since     1.0
     * @access    public
     * @return    string
     */
    public function toHtml() {
        global $CFG, $PAGE, $OUTPUT, $DB;
        if (empty($this->_value) || !array($this->_value) || count($this->_value) == 0) {
            return get_string('sortlist_no_data', 'grouptool');
        }
        if ($this->_flagfrozen) {
            return $this->getFrozenHtml();
        } else {

            // Generate draggable items - each representing 1 group!
            $dragableitems = "";
            $name = $this->getName(true);
            $showmembersstr = get_string('show_members', 'grouptool');
            $moveupstr = get_string('moveup', 'grouptool');
            $movedownstr = get_string('movedown', 'grouptool');
            $dragstr = get_string('drag', 'grouptool');
            $setactivestr = get_string('inactive');
            $setinactivestr = get_string('active');
            $deletestr = get_string('delete');
            $renamestr = get_string('rename');
            $groupdata = $this->getValue();
            $firstkey = key($groupdata);
            end($groupdata);
            $lastkey = key($groupdata);
            reset($groupdata);
            $table = new html_table();
            $table->data = array();
            foreach ($groupdata as $id => $group) {
                $row = array(); // Each group gets its own row!

                $namebase = $name.'['.$id.']';

                if (!key_exists('classes', $group)) {
                    $group['classes'] = array();
                } else {
                    if (!is_array($group['classes'])) {
                        $group['classes'] = array();
                    }
                }
                $chkboxattr = array(
                        'name'  => $namebase.'[selected]',
                        'type'  => 'checkbox',
                        'class' => 'checkbox_status class0 '.implode(' ', $group['classes']),
                        'value' => 1);
                if ($group['selected']) {
                    $chkboxattr['checked'] = 'checked';
                } else if (isset($chkboxattr['checked'])) {
                    unset($chkboxattr['checked']);
                }
                $advchkboxattr = $chkboxattr;
                $advchkboxattr['value'] = 0;
                $advchkboxattr['type'] = 'hidden';
                $hiddenattr = array(
                        'name'  => $namebase.'[sort_order]',
                        'type'  => 'hidden',
                        'value' => (!empty($group['sort_order']) ? $group['sort_order'] : 999999),
                        'class' => 'sort_order');
                $classelements = '';
                foreach($group['classes'] as $class) {
                    $classesattr = array(
                            'name'  => $namebase.'[classes][]',
                            'type'  => 'hidden',
                            'value' => $class,
                            'class' => 'hidden_classes');
                    $classelements .= html_writer::empty_tag('input', $classesattr);
                }

                $showmemberslink = html_writer::tag('a', $showmembersstr,
                                                    array('href' => 'somewhere',
                                                          'title' => $showmembersstr));
                $moveupattr = array('src'   => $OUTPUT->pix_url('i/up'),
                                    'alt'   => $moveupstr,
                                    'type'  => 'image',
                                    'name'  => 'moveup['.$id.']',
                                    'class' => 'moveupbutton');
                $this->_noSubmitButtons[] = 'moveup['.$id.']';
                if ($id == $firstkey) {
                    $moveupattr['style'] = "visibility:hidden;";
                }
                $moveupbutton = html_writer::empty_tag('input', $moveupattr);
                $movedownattr = array('src'   => $OUTPUT->pix_url('i/down'),
                                      'alt'   => $movedownstr,
                                      'type'  => 'image',
                                      'name'  => 'movedown['.$id.']',
                                      'class' => 'movedownbutton');
                $this->_noSubmitButtons[] = 'movedown['.$id.']';
                if ($id == $lastkey) {
                    $movedownattr['style'] = "visibility:hidden;";
                }
                $movedownbutton = html_writer::empty_tag('input', $movedownattr);
                $dragbutton = html_writer::empty_tag('img',
                                                     array('src'   => $OUTPUT->pix_url('i/dragdrop'),
                                                           'alt'   => $dragstr,
                                                           'class' => 'drag_image js_invisible'));
                $nameattr = array('name'  => $namebase.'[name]',
                                  'type'  => 'hidden',
                                  'value' => $group['name']);
                $nameblock = html_writer::tag('span', $group['name'], array('class'=>'text')).html_writer::empty_tag('input', $nameattr);
                // Todo add edit symbol and functionality for group names!
                $renameattr = array('src'   => $OUTPUT->pix_url('t/editstring'),
                                    'alt'   => $renamestr,
                                    'type'  => 'image',
                                    'name'  => 'rename['.$id.']',
                                    'class' => 'renamebutton');
                $renamebutton = html_writer::link(new moodle_url($PAGE->url, array('rename' => $id)),
                                                  html_writer::empty_tag('img', $renameattr),
                                                  array('class'=>$renameattr['class']));
                $nameblock .= $renamebutton;

                $drag = new html_table_cell($dragbutton);
                $drag->attributes['class'] = 'buttons';

                $deleteattr = array('src'   => $OUTPUT->pix_url('t/delete'),
                                    'alt'   => $deletestr,
                                    'name'  => 'delete['.$id.']',
                                    'class' => 'deletebutton',
                                    'id'    => 'delete_'.$id);
                $this->_noSubmitButtons[] = 'delete['.$id.']';
                $deletebutton = html_writer::link(new moodle_url($PAGE->url, array('delete'=>$id)),
                                                  html_writer::empty_tag('img', $deleteattr),
                                                  array('class'=>$deleteattr['class']));

                $row = array( 0 => new html_table_cell(html_writer::empty_tag('input', $advchkboxattr).
                                                       html_writer::empty_tag('input', $chkboxattr)),
                              1 => $drag,
                              2 => new html_table_cell($nameblock.
                                                       html_writer::empty_tag('input', $hiddenattr).
                                                       $classelements));
                $row[0]->attributes['class'] = 'checkbox_container';
                $row[2]->attributes['class'] = 'grpname';

                $additionalfields = array();
                foreach ($this->_options['add_fields'] as $key => $fielddata) {
                    if (!isset($group[$fielddata->name]) || $group[$fielddata->name] == null) {
                        $group[$fielddata->name] = $fielddata->stdvalue;
                    }
                    $attr = array(
                            'name' => $namebase."[$fielddata->name]",
                            'type' => $fielddata->type,
                            'value' => $group[$fielddata->name]);
                    $attr = array_merge($attr, $fielddata->attr);
                    $attr['id'] = html_writer::random_id($name);
                    if (!empty($fielddata->label)) {
                        $labelhtml = html_writer::tag('label', $fielddata->label,
                                                      array('for' => $attr['id']));
                    } else {
                        $labelhtml = "";
                    }
                    $currentfield = $labelhtml.html_writer::empty_tag('input', $attr);
                    $additionalfields[] = $currentfield;

                    $labelcell = new html_table_cell($labelhtml);
                    $labelcell->attributes['class'] = $fielddata->name." addfield";

                    $fieldcell = new html_table_cell(html_writer::empty_tag('input', $attr));
                    $fieldcell->attributes['class'] = $fielddata->name." addfield";
                    $row[] = $labelcell;
                    $row[] = $fieldcell;

                }
                // Todo: Add status toggle!
                if ($group['active']) {
                    $toggleattr = array('src'   => $OUTPUT->pix_url('t/go'),
                                        'alt'   => $setactivestr,
                                        'name'  => 'toggle['.$id.']',
                                        'class' => 'active');
                    $activestate = html_writer::empty_tag('input', array('type'  =>'hidden',
                                                                         'name'  => $namebase.'[active]',
                                                                         'value' => 1));
                } else {
                    $toggleattr = array('src'   => $OUTPUT->pix_url('t/stop'),
                                        'alt'   => $setinactivestr,
                                        'type'  => 'image',
                                        'name'  => 'toggle['.$id.']',
                                        'class' => 'inactive');
                    $activestate = html_writer::empty_tag('input', array('type'  =>'hidden',
                                                                         'name'  => $namebase.'[active]',
                                                                         'value' => 0));
                }
                $togglebutton = html_writer::link(new moodle_url($PAGE->url, array('toggle'=>$id)),
                                                  html_writer::empty_tag('img', $toggleattr),
                                                  array('class'=>'togglebutton '.$toggleattr['class']));
                $toggle = new html_table_cell($togglebutton.$activestate);
                $toggle->attributes['class'] = 'buttons';
                $row[] = $toggle;
                $moveup = new html_table_cell($moveupbutton);
                $moveup->attributes['class'] = 'buttons';
                $row[] = $moveup;
                $movedown = new html_table_cell($movedownbutton);
                $movedown->attributes['class'] = 'buttons';
                $row[] = $movedown;
                // TODO delete funktionality!
                $delete = new html_table_cell($deletebutton);
                $delete->attributes['class'] = 'buttons';
                $row[] = $delete;

                $row = new html_table_row($row);
                $row->attributes['class'] = 'draggable_item';
                if (!$group['active']) {
                    $row->attributes['class'] .= ' dimmed_text';
                }

                $rows[] = $row;
            }

            $table->data = $rows;
            $table->attributes['class'] .= 'drag_list table table-condensed';

            $controller = html_writer::link(new moodle_url($PAGE->url,
                                                           array('class_action' => 'select',
                                                                 'do_class_action' => '1')),
                                            get_string('all'),
                                            array('class' => 'simple_select_all')).
                          '/'.
                          html_writer::link(new moodle_url($PAGE->url,
                                                           array('class_action' => 'deselect',
                                                                 'do_class_action' => '1')),
                                            get_string('none'),
                                            array('class' => 'simple_select_none'));
            $controller = html_writer::tag('div', $controller);

            $tablehtml = $controller.html_writer::table($table).$controller;

            // Generate groupings-controls to select/deselect groupings!
            $checkboxcontroltitle = html_writer::tag('label', get_string('checkbox_control_header', 'grouptool'), array('for'=>'classes'));
            $helptext = $OUTPUT->render(new help_icon('checkbox_control_header', 'grouptool'));
            $checkboxcontroltitle = html_writer::tag('div', $checkboxcontroltitle.' '.$helptext,
                                                       array('class' => 'fitemtitle checkbox_controls_header'));

            $selectall = html_writer::tag('span', get_string('select', 'grouptool'));
            $selectnone = html_writer::tag('span', get_string('deselect', 'grouptool'));
            $inverseselection = html_writer::tag('span', get_string('invert', 'grouptool'));
            $checkboxcontrolelements = array();

            // Static controlelements for all elements!
            $options = array(html_writer::tag('option', $this->_options['all_string'], array('value' => '0')));

            if (!empty($this->_options['classes']) && is_array($this->_options['classes'])) {
                foreach ($this->_options['classes'] as $key => $class) {
                    if ($DB->count_records('groupings_groups', array('groupingid' => $class->id)) != 0) {
                        $options[] = html_writer::tag('option', $class->name, array('value' => $class->id));
                    } else {
                        // Disable empty groupings!
                        $options[] = html_writer::tag('option', $class->name, array('value' => $class->id, 'disabled' => 'disabled'));
                    }
                }
            }

            $checkboxcontrols = $checkboxcontroltitle;

            // Add Radiobuttons and Go Button TODO replace single buttons with radiobuttons + go-button
            $checkalllink = html_writer::empty_tag('input', array('name'  => 'class_action',
                                                                  'type'  => 'radio',
                                                                  'id'    => 'select',
                                                                  'value' => 'select',
                                                                  'class' => 'select_all')).
                            html_writer::tag('label', strip_tags($selectall), array('for'=>'select'));
            $checknonelink = html_writer::empty_tag('input', array('name'  => 'class_action',
                                                                   'type'  => 'radio',
                                                                   'id'    => 'deselect',
                                                                   'value' => 'deselect',
                                                                   'class' => 'select_none')).
                             html_writer::tag('label', strip_tags($selectnone), array('for'=>'deselect'));
            $checktogglelink = html_writer::empty_tag('input', array('name'  => 'class_action',
                                                                     'type'  => 'radio',
                                                                     'id'    => 'toggle',
                                                                     'value' => 'toggle',
                                                                     'class' => 'toggle_selection')).
                               html_writer::tag('label', strip_tags($inverseselection), array('for'=>'toggle'));;
            $submitbutton = html_writer::tag('button', get_string('go'),
                                             array('name' => 'do_class_action',
                                                   'value' => 'Go',));
            $this->_noSubmitButtons[] = 'do_class_action';

            $attr = array('class' => 'felement');
            $checkboxcontrols .= html_writer::tag('div',
                                                  html_writer::tag('select', implode("\n", $options),
                                                                   array('name' => 'classes[]',
                                                                         'multiple' => 'multiple')).
                                                  $checkalllink.$checknonelink.
                                                  $checktogglelink.$submitbutton,
                                                  $attr);

            $content = "";
            if (!empty($checkboxcontrols)) {
                $content .= $checkboxcontrols;
            }
            $content .= html_writer::tag('div', $tablehtml, array('class' => 'drag_area'));

            $html = html_writer::tag('div', $content, array('class' => 'fitem sortlist_container'));
            // Init JS!
            $PAGE->requires->yui_module('moodle-mod_grouptool-sortlist',
                                        'M.mod_grouptool.init_sortlist',
                                        null);
            return $html;
        }
    }
}
