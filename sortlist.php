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
 * This file contains a custom form element wich represents a sortable list
 *
 * @package       MoodleQuickForm_sortlist
 * @author        Philipp Hager
 * @copyright     2012 Philipp Hager
 * @since         Moodle 2.2.1
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

    // {{{ properties

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
    public $_flagFrozen = false;

    /**
     * Does the element support persistant data when frozen
     * @var       boolean
     * @since     1.3
     * @access    private
     */
    public $_persistantFreeze = false;

    /**
     * Options for the element
     * ['classes'] is an array containing all the classes names for which selector/deselector-links
     * should be displayed
     *
     */
    public $_options = array('classes'=>array(), 'add_fields'=>array(), 'all_string'=>'All');

    /**
     * value
     * $_value[]['name']
     * $_value[]['active']
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

    // }}}
    // {{{ constructor

    /**
     * Class constructor
     *
     * @access   public
     * @param    string  Element's name
     * @param    object  groupdata
     * @param    array   Options to control the element's display
     * @param    mixed   Either a typical HTML attribute string or an associative array
     */
    public function __construct($elementName = null, $options = array(), $attributes = null) {
        $this->HTML_QuickForm_element($elementName, '', $attributes);
        $this->_persistantFreeze = true;
        $this->_appendName = true;
        $this->_type = 'sortlist';
        // set the options, do not bother setting bogus ones
        if (is_array($options)) {
            foreach ($options as $name => $value) {
                if (isset($this->_options[$name])) {
                    if (is_array($value) && is_array($this->_options[$name])) {
                        $this->_options[$name] = @array_merge($this->_options[$name], $value);
                    } else {
                        $this->_options[$name] = $value;
                    }
                }
            }
        }
    }

    // }}}
    // {{{ apiVersion()

    /**
     * Returns the current API version
     *
     * @since     1.0
     * @access    public
     * @return    float
     */
    public function apiVersion() {
        return 2.0;
    } // end func apiVersion

    // }}}
    // {{{ getType()

    /**
     * Returns element type
     *
     * @since     1.0
     * @access    public
     * @return    string
     */
    public function getType() {
        return $this->_type;
    } // end func getType

    // }}}
    // {{{ setName()

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
    } //end func setName

    // }}}
    // {{{ getName()

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
    } //end func getName

    // }}}
    // {{{ swapElements()

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
            //reorder Elements
            uasort($this->_value, array(&$this, "cmp"));
        } else {
            print_error('Item swap not possible, 1 of the Elements doesn\'t exist!');
        }
    }

    // }}}
    // {{{ move1up()

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

    // }}}
    // {{{ move1down()

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

    // }}}
    // {{{ setValue()

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
    } // end func setValue

    // }}}
    // {{{ grpcmp()

    /**
     * compares if two groups are in correct order
     */
    public function cmp($element1, $element2) {
        if ($element1['sort_order'] == $element2['sort_order']) {
            //if (strcmp($element1['name'],$element2['name'])) {
            return 0;
            /*} else {
             return strcmp($element1['name'],$element2['name']) >= 0 ? +1 : -1;
            }*/
        } else {
            return $element1['sort_order'] > $element2['sort_order'] ? +1 : -1;
        }
    }

    // }}}
    // {{{ getValue()

    /**
     * Returns the value of the form element
     *
     * @since     1.0
     * @access    public
     * @return    mixed
     */
    public function getValue($sorted=true) {
        $this->_refresh_element_order();
        $this->_refresh_active_state();
        if (!$sorted) {
            return $this->_clean_addfields($this->_value);
        }
        $elementdata = $this->_clean_addfields($this->_value);
        uasort($elementdata, array(&$this, "cmp"));
        return $elementdata;
    } // end func getValue

    // }}}
    // {{{ freeze()

    /**
     * Freeze the element so that only its value is returned
     *
     * @access    public
     * @return    void
     */
    public function freeze() {
        $this->_flagFrozen = true;
    } //end func freeze

    // }}}
    // {{{ unfreeze()

    /**
     * Unfreezes the element so that it becomes editable
     *
     * @access public
     * @return void
     * @since  3.2.4
     */
    public function unfreeze() {
        $this->_flagFrozen = false;
    }

    // }}}
    // {{{ getFrozenHtml()

    /**
     * Returns the value of field without HTML tags
     *
     * @since     1.0
     * @access    public
     * @return    string
     */
    public function getFrozenHtml() {
        $value = $this->getValue();
        return ('' != $value? htmlspecialchars($value): '&nbsp;') .
        $this->_getPersistantData();
    } //end func getFrozenHtml

    // }}}
    // {{{ _getPersistantData()

    /**
     * Used by getFrozenHtml() to pass the element's value if _persistantFreeze is on
     *
     * @access private
     * @return string
     */
    public function _getPersistantData() {
        if (!$this->_persistantFreeze) {
            return '';
        } else {
            //generate draggable items - each representing 1 group
            $items = "";
            $name = $this->getName(true);
            $groupdata = $this->getValue();
            $firstkey = key($groupdata);
            end($groupdata);
            $lastkey = key($groupdata);
            reset($groupdata);
            foreach ($groupdata as $id => $group) {
                if (!key_exists('classes', $group)) {
                    $group['classes'] = array('');
                }

                $namebase = $name.'['.$id.']';
                $chkbox_attr = array(
                        'name'     => $namebase.'[active]',
                        'type'     => 'checkbox',
                        'disabled' => 'disabled',
                        'value'    => $group['active']);
                if ($group['active']) {
                    $chkbox_attr['checked'] = 'checked';
                } else if (isset($chkbox_attr['checked'])) {
                    unset($chkbox_attr['checked']);
                }
                $advchkbox_attr = $chkbox_attr;
                $advchkbox_attr['value'] = $group['active'];
                $advchkbox_attr['type'] = 'hidden';
                $hidden_attr = array(
                        'name' => $namebase.'[sort_order]',
                        'type' => 'hidden',
                        'value' => $group['sort_order'],
                        'class' => 'sort_order');
                $moveup_attr = array( 'src'=>$CFG->wwwroot.'/mod/grouptool/pix/moveup.png',
                        'alt'=>$move_up_str,
                        'type'=>'image',
                        'name'=>'moveup['.$id.']',
                        'disabled'=>'disabled',
                        'class'=>'moveupbutton');
                if ($id == $firstkey) {
                    $moveup_attr['style'] = "visibility:hidden;";
                }
                $moveup_button = html_writer::empty_tag('input', $moveup_attr);
                $movedown_attr = array( 'src'=>$CFG->wwwroot.'/mod/grouptool/pix/movedown.png',
                        'alt'=>$move_down_str,
                        'type'=>'image',
                        'name'=>'movedown['.$id.']',
                        'disabled'=>'disabled',
                        'class'=>'movedownbutton');
                $this->_noSubmitButtons[]='movedown['.$id.']';
                if ($id == $lastkey) {
                    $movedown_attr['style'] = "visibility:hidden;";
                }
                $movedown_button = html_writer::empty_tag('input', $movedown_attr);
                $name_attr = array( 'name'=>$namebase.'[name]',
                        'type'=>'hidden',
                        'value'=>$group['name']);
                $nameblock = $group['name'].html_writer::empty_tag('input', $name_attr);
                $temp = html_writer::empty_tag('input', $advchkbox_attr).
                        html_writer::empty_tag('input', $chkbox_attr);
                $left = html_writer::tag('span', $temp, array('class'=>'checkbox_container'));
                $left .= "\n\t";
                $left .= html_writer::tag('span', $nameblock.
                                                  html_writer::empty_tag('input', $hidden_attr),
                                          array('class'=>'grpname'));
                $left .= "\n\t";
                $left = html_writer::tag('div', $left, array('class'=>'left'));
                $additional_fields = "";
                foreach ($this->_options['add_fields'] as $key => $fielddata) {
                    if (!isset($group[$fielddata->name]) || $group[$fielddata->name] == null) {
                        $group[$fielddata->name] = $fielddata->stdvalue;
                    }
                    $attr = array(
                            'name' => $namebase."[$fielddata->name]",
                            'type' => $fielddata->type,
                            'disabled' => 'disabled',
                            'value' => $group[$fielddata->name]);
                    $attr = array_merge($attr, $fielddata->attr);
                    $label = html_writer::tag('label', $fielddata->label,
                                              array('for'=>$namebase."[$fielddata->name]"));
                    $element = html_writer::empty_tag('input', $attr);
                    $additional_fields .= html_writer::tag('span', $label.$element,
                                                           array('class'=>$fielddata->name))."\n\t";
                }

                $right = html_writer::tag('div', $additional_fields.
                        html_writer::tag('span', "\n\t\t".
                                $moveup_button."&nbsp;\n\t\t".
                                $movedown_button."&nbsp;\n\t\t".
                                $drag_button."\n\t",
                                array('class'=>'buttons'))."\n\t",
                        array('class'=>'right'));
                $item_content = $right.$left;

                $dragable_items .= "\n".
                                   html_writer::tag('li',
                                                    "\n\t".$item_content."\n",
                                                    array('class'=>'draggable_item')).
                                   "\n";
            }

            $dragable_list = html_writer::tag('ul', $dragable_items, array('class'=>'drag_list'));

            $content = html_writer::tag('div', $dragable_list, array('class'=>'drag_area'));

            $html = html_writer::tag('div', $content, array('class'=>'sortlist_container'));

            return $html;
        }
    }

    // }}}
    // {{{ isFrozen()

    /**
     * Returns whether or not the element is frozen
     *
     * @since     1.3
     * @access    public
     * @return    bool
     */
    public function isFrozen() {
        return $this->_flagFrozen;
    } // end func isFrozen

    // }}}
    // {{{ setPersistantFreeze()

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
        $this->_persistantFreeze = $persistant;
    } //end func setPersistantFreeze

    // }}}
    // {{{ setLabel()

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
    } //end func setLabel

    // }}}
    // {{{ getLabel()

    /**
     * Returns display text for the element
     *
     * @since     1.3
     * @access    public
     * @return    string
     */
    public function getLabel() {
        return $this->_label;
    } //end func getLabel

    // }}}
    // {{{ _findValue()

    /**
     * Tries to find the element value from the values array
     *
     * @since     2.7
     * @access    private
     * @return    mixed
     */
    public function _findValue(&$values, $elementName = null) {
        if (empty($values)) {
            return null;
        }
        if ($elementName == null) {
            $elementName = $this->getName();
        }
        if (isset($values[$elementName])) {
            return $values[$elementName];
        } else if (strpos($elementName, '[')) {
            $myVar = "['" . str_replace(array(']', '['), array('', "']['"), $elementName) . "']";
            return eval("return (isset(\$values$myVar)) ? \$values$myVar : null;");
        } else {
            return null;
        }
    } //end func _findValue

	private function _clean_addfields($data) {
		if(!is_array($data)) {
			return $data;
		}
		
		foreach($data as $id => $group) {
			foreach($this->_options['add_fields'] as $key => $fielddata) {
				if(empty($fielddata->param_type)) {
					$fielddata->param_type = PARAM_TEXT;
				}
				if(isset($data[$id][$fielddata->name])) {
					$data[$id][$fielddata->name] = clean_param($group[$fielddata->name], $fielddata->param_type);
				}
			}
		}
		return $data;
	}
	
    // }}}
    // {{{ onQuickFormEvent()

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
                // constant values override both default and submitted ones
                // default values are overriden by submitted
                $value = $this->_clean_addfields($this->_findValue($caller->_constantValues));
                if (null === $value) {
                    $value = $this->_clean_addfields($this->_findValue($caller->_submitValues));
                    // let the form-handling-php belief there was no submission
                    // if it was just a moveup/movedown submit
                    if (optional_param_array('moveup', 0, PARAM_INT)
                            || optional_param_array('movedown', 0, PARAM_INT)) {
                        $caller->_flagSubmitted = false;
                    }
                    //same for the checkbox-controller buttons
                    if (optional_param_array('select_class', 0, PARAM_INT)
                            || optional_param_array('deselect_class', 0, PARAM_INT)
                            || optional_param_array('toggle_class', 0, PARAM_INT)) {
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
    } // end func onQuickFormEvent

    // }}}
    // {{{ accept()

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
        $renderer->_templates[$this->getName()] = '<div class="qfelement<!-- BEGIN error --> error<!-- END error -->"><!-- BEGIN error --><span class="error">{error}</span><br /><!-- END error -->{element}</div>';
        $renderer->renderElement($this, $required, $error);
    } // end func accept

    // }}}
    // {{{ _generateId()

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

    // }}}
    // {{{ exportValue()

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

    // }}}
    // {{{ _prepareValue()

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

    // }}}

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
        $this->updateAttributes(array('type'=>$type));
    } // end func setType

    /**
     * updates the element active-state if corresponding params are set
     */
    public function _refresh_active_state() {
        global $COURSE;
        $select = optional_param_array('select_class', null, PARAM_INT);
        $deselect = optional_param_array('deselect_class', null, PARAM_INT);
        $toggle = optional_param_array('toggle_class', null, PARAM_INT);

        if ($select != null || $deselect != null || $toggle != null) {
            $keys = array();

            if ($select != null) {
                $action = "select";
                $groupingid = reset(array_keys($select));
            } else if ($deselect != null) {
                $action = "deselect";
                $groupingid = reset(array_keys($deselect));
            } else if ($toggle != null) {
                $action = "toggle";
                $groupingid = reset(array_keys($toggle));
            }

            $groups = groups_get_all_groups($COURSE->id, 0, $groupingid);

            foreach ($groups as $current) {
                switch($action) {
                    case 'select':
                        $this->_value[$current->id]['active'] = 1;
                        break;
                    case 'deselect':
                        $this->_value[$current->id]['active'] = 0;
                        break;
                    case 'toggle':
                        $next = !$this->_value[$current->id]['active'];
                        $this->_value[$current->id]['active'] = $next;
                        break;
                }
            }

            $this->_flagSubmitted = false;
        }
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
        global $CFG, $PAGE, $OUTPUT;

        if (empty($this->_value) || !array($this->_value) || count($this->_value) == 0) {
            return get_string('sortlist_no_data', 'grouptool');
        }
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {

            //generate draggable items - each representing 1 group
            $dragable_items = "";
            $name = $this->getName(true);
            $show_members_str = get_string('show_members', 'grouptool');
            $move_up_str = get_string('moveup', 'grouptool');
            $move_down_str = get_string('movedown', 'grouptool');
            $drag_str = get_string('drag', 'grouptool');
            $groupdata = $this->getValue();
            $firstkey = key($groupdata);
            end($groupdata);
            $lastkey = key($groupdata);
            reset($groupdata);
            foreach ($groupdata as $id => $group) {
                $table = new html_table();
                $table->data = array();

                $namebase = $name.'['.$id.']';

                if (!key_exists('classes', $group)) {
                    $group['classes'] = array();
                } else {
                    if (!is_array($group['classes'])) {
                        $group['classes'] = array();
                    }
                }
                $chkbox_attr = array(
                        'name' => $namebase.'[active]',
                        'type' => 'checkbox',
                        'class' => 'checkbox_status '.implode(' ', $group['classes']),
                        'value' => 1);
                if ($group['active']) {
                    $chkbox_attr['checked'] = 'checked';
                } else if (isset($chkbox_attr['checked'])) {
                    unset($chkbox_attr['checked']);
                }
                $advchkbox_attr = $chkbox_attr;
                $advchkbox_attr['value'] = 0;
                $advchkbox_attr['type'] = 'hidden';
                $hidden_attr = array(
                        'name' => $namebase.'[sort_order]',
                        'type' => 'hidden',
                        'value' => (!empty($group['sort_order']) ? $group['sort_order'] : 999999),
                        'class' => 'sort_order');

                $show_members_link = html_writer::tag('a', $show_members_str,
                        array(
                                'href'=>'somewhere',
                                'title'=>$show_members_str));
                $moveup_attr = array( 'src'=>$CFG->wwwroot.'/mod/grouptool/pix/moveup.png',
                        'alt'=>$move_up_str,
                        'type'=>'image',
                        'name'=>'moveup['.$id.']',
                        'class'=>'moveupbutton');
                $this->_noSubmitButtons[]='moveup['.$id.']';
                if ($id == $firstkey) {
                    $moveup_attr['style'] = "visibility:hidden;";
                }
                $moveup_button = html_writer::empty_tag('input', $moveup_attr);
                $movedown_attr = array( 'src'=>$CFG->wwwroot.'/mod/grouptool/pix/movedown.png',
                        'alt'=>$move_down_str,
                        'type'=>'image',
                        'name'=>'movedown['.$id.']',
                        'class'=>'movedownbutton');
                $this->_noSubmitButtons[]='movedown['.$id.']';
                if ($id == $lastkey) {
                    $movedown_attr['style'] = "visibility:hidden;";
                }
                $movedown_button = html_writer::empty_tag('input', $movedown_attr);
                $drag_button = html_writer::empty_tag('img', array(
                        'src'=>$CFG->wwwroot.'/mod/grouptool/pix/drag.png',
                        'alt'=>$drag_str,
                        'class' => 'drag_image js_invisible'));
                $name_attr = array( 'name'=>$namebase.'[name]',
                        'type'=>'hidden',
                        'value'=>$group['name']);
                if (strlen($group['name']) > 25) {
                    $nameblock = substr($group['name'], 0, 17).'...'.substr($group['name'], -5, 5).
                                 html_writer::empty_tag('input', $name_attr);
                } else {
                    $nameblock = $group['name'].html_writer::empty_tag('input', $name_attr);
                }

                $rows = array();
                $row = array( new html_table_cell(html_writer::empty_tag('input', $advchkbox_attr).
                                                  html_writer::empty_tag('input', $chkbox_attr)),
                              new html_table_cell($nameblock.
                                                  html_writer::empty_tag('input', $hidden_attr)),
                              new html_table_cell($moveup_button),
                              new html_table_cell($movedown_button),
                              new html_table_cell($drag_button));
                $rows[0] = new html_table_row($row);

                $additional_fields = array();
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
                                                      array('for'=>$attr['id']));
                    } else {
                        $labelhtml = "";
                    }
                    $currentfield = $labelhtml.html_writer::empty_tag('input', $attr);
                    $additional_fields[] = $currentfield;
                    $cell = new html_table_cell($currentfield);
                    $cell->attributes['class'] = $fielddata->name." addfield";
                    $rows[] = new html_table_row(array($cell));
                }
                $rows[0]->cells[0]->rowspan = count($additional_fields)+1;
                $rows[0]->cells[0]->attributes['class'] = 'checkbox_container';
                $rows[0]->cells[1]->attributes['class'] = 'grpname';
                $rows[0]->cells[2]->rowspan = count($additional_fields)+1;
                $rows[0]->cells[2]->attributes['class'] = 'buttons';
                $rows[0]->cells[3]->rowspan = count($additional_fields)+1;
                $rows[0]->cells[3]->attributes['class'] = 'buttons';
                $rows[0]->cells[4]->rowspan = count($additional_fields)+1;
                $rows[0]->cells[4]->attributes['class'] = 'buttons';

                $table->data = $rows;
                $item_content = html_writer::table($table);
                $dragable_items .= "\n".html_writer::tag('li', "\n\t".$item_content."\n",
                                                         array('class'=>'draggable_item'))."\n";
            }

            $dragable_list = html_writer::tag('ul', $dragable_items, array('class'=>'drag_list'));

            //generate groupings-controls to select/deselect groupings
            $checkbox_control_title = get_string('checkbox_control_header', 'grouptool');
            $checkbox_control_title = html_writer::tag('div', $checkbox_control_title,
                                                       array('class'=>'checkbox_controls_header'));

            $select_all = html_writer::tag('span', get_string('select_all', 'grouptool'));
            $select_none = html_writer::tag('span', get_string('select_none', 'grouptool'));
            $inverse_selection = html_writer::tag('span', get_string('select_inverse',
                                                                     'grouptool'));
            $checkbox_control_elements = array();

            //static controlelements for all elements!
            $this->_noSubmitButtons[]='select[all]';
            $check_all_link = html_writer::tag('button', $select_all,
                                               array('name'  => 'select_class[0]',
                                                     'value' => 'all',
                                                     'type'  => 'submit',
                                                     'title' => strip_tags($select_all),
                                                     'class' => 'select_all'));
            $this->_noSubmitButtons[]='deselect[all]';
            $check_none_link = html_writer::tag('button', $select_none,
                                                array('name'  => 'deselect_class[0]',
                                                      'value' => 'all',
                                                      'type'  => 'submit',
                                                      'title' => strip_tags($select_none),
                                                      'class' => 'select_none'));
            $this->_noSubmitButtons[]='toggle[all]';
            $check_toggle_link = html_writer::tag('button', $inverse_selection,
                                                  array('name'  => 'toggle_class[0]',
                                                        'value' => 'all',
                                                        'type'  => 'submit',
                                                        'title' => strip_tags($inverse_selection),
                                                        'class' => 'toggle_selection'));
            $check_name = html_writer::tag('span', $this->_options['all_string'],
                                           array('class'=>'name'));
            $attr = array('class'=>'checkbox_control checkbox_status');
            $checkbox_control_elements[] = html_writer::tag('div', $check_name.$check_all_link.
                                                                   $check_none_link.
                                                                   $check_toggle_link,
                                                            $attr);

            if (!empty($this->_options['classes']) && is_array($this->_options['classes'])) {
                foreach ($this->_options['classes'] as $key => $class) {
                    $this->_noSubmitButtons[]='select['.$class->id.']';
                    $selectname = 'select_class['.$class->id.']';
                    $check_all_link = html_writer::tag('button', $select_all,
                                                       array('name'  => $selectname,
                                                             'value' => $class->id,
                                                             'type'  => 'submit',
                                                             'title' => strip_tags($select_all),
                                                             'class' => 'select_all'));
                    $this->_noSubmitButtons[]='deselect['.$class->id.']';
                    $deselectname = 'deselect_class['.$class->id.']';
                    $check_none_link = html_writer::tag('button', $select_none,
                                                        array('name'  => $deselectname,
                                                              'value' => $class->id,
                                                              'type'  => 'submit',
                                                              'title' => strip_tags($select_none),
                                                              'class' => 'select_none'));
                    $this->_noSubmitButtons[]='toggle['.$class->id.']';
                    $togglename = 'toggle_class['.$class->id.']';
                    $toggletitle = strip_tags($inverse_selection);
                    $check_toggle_link = html_writer::tag('button', $inverse_selection,
                                                          array('name'  => $togglename,
                                                                'value' => $class->id,
                                                                'type'  => 'submit',
                                                                'title' => $toggletitle,
                                                                'class' => 'toggle_selection'));
                    $check_name = html_writer::tag('span', $class->name, array('class'=>'name'));
                    $attr = array('class'=>'checkbox_control class'.$class->id);
                    $checkbox_control_elements[] = html_writer::tag('div',
                                                                    $check_name.$check_all_link.
                                                                    $check_none_link.
                                                                    $check_toggle_link,
                                                                    $attr);
                }
            }

            $checkbox_controls = $checkbox_control_title.implode("", $checkbox_control_elements);
            $content = "";
            if (!empty($checkbox_controls)) {
                $content .= html_writer::tag('div', $checkbox_controls,
                                             array('class'=>'checkbox_controls'));
            }
            $content .= html_writer::tag('div', $dragable_list, array('class'=>'drag_area'));

            $html = html_writer::tag('div', $content, array('class'=>'sortlist_container'));
            /*init JS*/
            $PAGE->requires->yui_module('moodle-mod_grouptool-sortlist',
                    'M.mod_grouptool.init_sortlist',
                    null);
            return $html;
        }
    } //end func toHtml


    // }}}
} // end class MoodleQuickform_sortgrplist}
