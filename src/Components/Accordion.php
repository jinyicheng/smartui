<?php

namespace SmartUI\Components;

use ReflectionException;
use SmartUI\UI;
use SmartUI\Util;

class Accordion extends UI
{

    private $_options_map = [
        'global_icons' => []
    ];

    private $_structure = [
        'panel' => [],
        'id' => '',
        'header' => [],
        'content' => [],
        'expand' => [],
        'padding' => [],
        'icons' => [],
        'options' => []
    ];

    /**
     * Accordion constructor.
     * @param $panels
     * @param array $options
     */
    public function __construct($panels, $options = [])
    {
        $this->_options_map['global_icons'] = array(parent::$icon_source . '-lg ' . parent::$icon_source . '-chevron-down pull-right', '' . parent::$icon_source . '-lg ' . parent::$icon_source . '-chevron-up pull-right');
        $this->_init_structure($panels, $options);
    }

    /**
     * @param $panels
     * @param array $user_options
     */
    private function _init_structure($panels, $user_options = [])
    {
        $this->_structure = Util::array_to_object($this->_structure);
        $this->_structure->options = Util::set_array_prop_def($this->_options_map, $user_options);
        $this->_structure->id = Util::create_id(true);
        $this->_structure->panel = $panels;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->_structure->{$name})) {
            return $this->_structure->{$name};
        }
        parent::err('Undefined structure property: ' . $name);
        return null;
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (isset($this->_structure->{$name})) {
            $this->_structure->{$name} = $value;
            return;
        }
        parent::err('Undefined structure property: ' . $name);
    }

    /**
     * @param $name
     * @param $args
     * @return mixed|object|void|null
     * @throws ReflectionException
     */
    public function __call($name, $args)
    {
        return parent::_call($this, $this->_structure, $name, $args);
    }

    /**
     * @param bool $return
     * @return string|void
     */
    public function print_html($return = false)
    {
        $structure = $this->_structure;

        $panels = Util::get_property_value($structure->panel, array(
            'if_closure' => function ($panels) {
                return Util::run_callback($panels, array($this));
            },
            'if_other' => function ($panels) {
                parent::err('parent::Accordion:panel requires array');
                return null;
            }
        ));

        if (!is_array($panels)) {
            parent::err("parent::Accordion:panel requires array");
            return null;
        }

        $panel_html_list = [];
        foreach ($panels as $panel_id => $panel_prop) {

            $panel_structure = array(
                'header' => isset($structure->header[$panel_id]) ? $structure->header[$panel_id] : '',
                'content' => isset($structure->content[$panel_id]) ? $structure->content[$panel_id] : '',
                'expand' => isset($structure->expand[$panel_id]) ? $structure->expand[$panel_id] : false,
                'padding' => isset($structure->padding[$panel_id]) ? $structure->padding[$panel_id] : null,
            );

            $new_panel_prop = Util::get_clean_structure($panel_structure, $panel_prop, array($this, $panels), 'header');

            foreach ($new_panel_prop as $panel_prop_key => $panel_prop_vaue) {
                $new_panel_prop_value = Util::get_property_value($panel_prop_vaue, array(
                    'if_closure' => function ($prop_value) use ($panels) {
                        return Util::run_callback($prop_value, array($this, $panels));
                    }
                ));
                $new_panel_prop[$panel_prop_key] = $new_panel_prop_value;
            }

            // header
            $header_structure = array(
                'content' => '',
                'container' => 'h4',
                'icons' => isset($structure->icons[$panel_id]) ? $structure->icons[$panel_id] : $structure->options['global_icons']
            );
            $new_header_prop = Util::get_clean_structure($header_structure, $new_panel_prop['header'], array($this, $panels), 'content');

            $a_classes = [];
            if (!$new_panel_prop['expand']) $a_classes[] = 'collapsed';

            $a_attr = [];
            $a_attr[] = 'data-parent="#' . $structure->id . '"';
            $a_attr[] = 'href="#' . $panel_id . '"';
            $a_attr[] = 'data-toggle="collapse"';

            $icons = is_array($new_header_prop['icons']) ? implode(' ', array_map(function ($icon) {
                return '<i class="' . parent::$icon_source . ' ' . $icon . '"></i> ';
            }, $new_header_prop['icons'])) : $new_header_prop['icons'];

            $body_classes = [];
            $body_classes[] = 'panel-body';

            if (isset($new_panel_prop['padding'])) {
                $body_classes[] = $new_panel_prop['padding'] ? 'padding-' . $new_panel_prop['padding'] : 'no-padding';
            }


            $panel_html = '<div class="panel panel-default">';
            $panel_html .= '	<div class="panel-heading">';
            $panel_html .= '		<' . $new_header_prop['container'] . ' class="panel-title">';

            $panel_html .= '			<a ' . implode(' ', $a_attr) . ' class="' . implode(' ', $a_classes) . '"> ';
            $panel_html .= $icons;
            $panel_html .= $new_header_prop['content'];
            $panel_html .= '			</a>';

            $panel_html .= '		</' . $new_header_prop['container'] . '>';
            $panel_html .= '	</div>';

            $panel_html .= '	<div id="' . $panel_id . '" class="panel-collapse collapse ' . ($new_panel_prop['expand'] ? 'in' : '') . '">';
            $panel_html .= '		<div class="' . implode(' ', $body_classes) . '">';
            $panel_html .= $new_panel_prop['content'];
            $panel_html .= '		</div>';
            $panel_html .= '	</div>';
            $panel_html .= '</div>';

            $panel_html_list[] = $panel_html;
        }


        $result = '<div class="panel-group smart-accordion-default" id="' . $structure->id . '">';
        $result .= implode('', $panel_html_list);
        $result .= '</div>';

        if ($return) return $result;
        else echo $result;
    }
}