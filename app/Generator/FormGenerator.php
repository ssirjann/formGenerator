<?php

namespace App\Generators;

class FormGenerator
{
    private $name;
    private $elements;
    private $formPath;

    private $types = [
        'checkbox', 'password', 'radio', 'text', 'email', 'date', 'file', 'number', 'submit', 'select', 'text',
    ];
    private $validate = ['email' => 'email', 'date' => 'date', 'file' => 'file', 'number' => 'number',];
    private $multiple = ['checkbox', 'radio', 'select'];
    private $special = ['submit', 'checkbox', 'radio', 'select', 'password'];

    public function __construct($name, $elements)
    {
        $this->name     = $name;
        $this->elements = $elements;

        $this->convertElementsToArray();
    }

    public function handle()
    {
        $this->validate();

        $this->generate();
    }

    private function validate()
    {
        if (!$this->validName()) {
            throw new \Exception("Invalid name provided");
        }

        if (!$this->validElements()) {
            throw new \Exception("Invalid Elements provided");
        }
    }

    private function validName()
    {
        return is_string($this->name);
    }

    private function validElements()
    {
        $valid = true;
        $valid &= is_array($this->elements);
        foreach ($this->elements as $el) {
            $valid &= is_array($el);
            $valid &= is_string($el[0]);
            $valid &= in_array($el[1], $this->types);

            if (in_array($el[1], $this->multiple)) {
                if (is_numeric($el[2])) {
                    $count = $el[2];

                    $valid &= count($el) - 3 >= $count; // check if options = number given
                } else if (strpos($el[2], '$') === 0 && strlen($el[2]) > 1) {
                    $valid &= true;
                } else {
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    private function generate()
    {
        $this->generateDirectories();

        $elems = $this->getFormattedElems();

        $this->populateView($elems);

        $this->populateRequest($elems);
    }

    private function getFormattedElems()
    {
        $elems = [];

        foreach ($this->elements as $index => $el) {
            $elems[] = $this->getFormattedElem($el);;
        }

        return $elems;
    }

    private function convertElementsToArray()
    {
        foreach ($this->elements as &$element) {
            $element = preg_split('/(?<=[^\\\\])(\s)/', $element);

            foreach ($element as &$arg) {
                $arg = str_replace('\ ', " ", $arg);
            }
        }
    }

    private function getFormattedElem($el)
    {
        $element         = [];
        $index           = 0;
        $element['name'] = $el[$index++];
        $element['type'] = $el[$index++];

        if (in_array($element['type'], $this->multiple)) {
            $this->handleOptions($el, $index, $element);
        }

        if (isset($el[$index])) {
            $element['value'] = $el[$index];
        }

        return $element;
    }

    private function handleOptions($el, &$index, &$element)
    {
        if (is_numeric($el[$index])) {
            $count              = $el[$index++];
            $element['options'] = array_slice($el, $index, $count);
            $index              += $count;
        } else {
            $element['variable'] = $el[$index++];
        }
    }

    private function generateDirectories()
    {
        $this->createBladeDirectory();
        $this->createRequestsDirectory();
    }

    private function createBladeDirectory()
    {
        $path = resource_path() . '/views/' . strtolower($this->name);

        $this->mkdir($path);

        $this->formPath = $path;
    }

    private function createRequestsDirectory()
    {
        $path = app_path() . '/Http/Requests/' . ucfirst($this->name);

        $this->mkdir($path);
    }

    /**
     * @param $path
     *
     * @return bool
     * @throws \Exception
     */
    private function mkdir($path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function populateView($elems = null)
    {
        $elems  = $elems ?: $this->getFormattedElems();
        $form   = $this->getFormHtmlString($elems);
        $create = $this->getCreateHtmlString();
        $edit   = $this->getEditHtmlString();

        file_put_contents($this->formPath . '/form.blade.php', $form);
        file_put_contents($this->formPath . '/create.blade.php', $create);
        file_put_contents($this->formPath . '/edit.blade.php', $edit);
    }

    private function populateRequest($elems = null)
    {
        $elems = $elems ?: $this->getFormattedElems();

        $rulesString = '[';

        foreach ($elems as $elem) {
            $rulesString .= $this->getRuleString($elem);
        }

        $rulesString .= ']';

    }

    private function getFieldHtml($field)
    {
        if (in_array($field['type'], $this->special)) {
            $html = $this->specialFieldHtml($field);
        } else {
            $html = $this->generalFieldHtml($field);
        }

        return $html;
    }

    /**
     * @param $elems
     *
     * @return string
     */
    private function getFormHtmlString($elems)
    {
        $content = '';

        foreach ($elems as $el) {
            $content .= $this->getFieldHtml($el);
        }

        return $content;
    }

    private function specialFieldHtml($field)
    {
        $method = $field['type'] . 'FieldHtml';

        return $this->$method($field);
    }

    private function generalFieldHtml($field)
    {
        return <<<HTML
        
    <div class="form-group {{ \$errors->has('{$field['name']}') ? ' has-error' : '' }}">
        <label class="col-sm-2 control-label">{$field['name']}</label>
        <div class="col-md-10">
            {!! Form::{$field['type']}('{$field['name']}', null, ['class' => 'form-control']) !!}
            @if (\$errors->has('{$field['name']}'))
                <span class="help-block">
                    <strong>{{ \$errors->first('{$field['name']}') }}</strong>
                </span>
            @endif
        </div>
    </div>
HTML;
    }

    private function submitFieldHtml($field)
    {
        return <<<HTML
        
        <div class="col-md-10 col-md-offset-2">
	        {!! Form::submit("Submit", ['class' => 'btn btn-primary']) !!}
	    </div>
HTML;
    }

    private function passwordFieldHtml($field)
    {
        $password          = $this->passwordHtml($field);
        $field['name']     .= '_confirmation';
        $passwordConfirmed = $this->passwordHtml($field);

        return $password . $passwordConfirmed;
    }

    private function passwordHtml($field)
    {
        return <<<HTML
        
    <div class="form-group {{ \$errors->has('{$field['name']}') ? ' has-error' : '' }}">
        <label class="col-sm-2 control-label">{$field['name']}</label>
        <div class="col-md-10">
            {!! Form::{$field['type']}('{$field['name']}', ['class' => 'form-control']) !!}
            @if (\$errors->has('{$field['name']}'))
                <span class="help-block">
                    <strong>{{ \$errors->first('{$field['name']}') }}</strong>
                </span>
            @endif
        </div>
    </div>
HTML;
    }

    private function checkboxFieldHtml($field)
    {
        $html = $this->checkboxRadioHtml($field);

        return $html;
    }

    private function radioFieldHtml($field)
    {
        $html = $this->checkboxRadioHtml($field);

        return $html;
    }

    private function selectFieldHtml($field)
    {
        $options = isset($field['variable']) ? $field['variable'] : $this->getOptionsAsString($field[]['options']);

        return <<<HTML
        <div class="form-group {{ \$errors->has('{$field['name']}') ? ' has-error' : '' }}">
            <label class="col-sm-2 control-label">{$field['name']}</label>
            <div class="col-md-10">
            {!! Form::select('{$field['name']}', $options, null,
                ['class'=> 'form-control']) !!}
                @if (\$errors->has('{$field['name']}'))
                    <span class="help-block">
                        <strong>{{ \$errors->first('{$field['name']}') }}</strong>
                    </span>
                @endif
            </div>
        </div>
HTML;
    }

    private function fileFieldHtml($field)
    {
        return <<<HTML

        <div class="form-group {{ \$errors->has('{$field['name']}') ? ' has-error' : '' }}">
            <label class="col-sm-2 control-label">{$field['name']}</label>
            <div class="col-md-10">
            {!! Form::file('{$field['name']}') 
                @if (\$errors->has('{$field['name']}'))
                    <span class="help-block">
                        <strong>{{ \$errors->first('{$field['name']}') }}</strong>
                    </span>
                @endif
            </div>
        </div>
HTML;

    }

    /**
     * @param $field
     *
     * @return string
     */
    private function checkboxRadioHtml($field)
    {
        $options = isset($field['variable']) ? $field['variable'] : $this->getOptionsAsString($field['options']);
        $html    = <<<HTML

    <div class="form-group {{ \$errors->has('{$field['name']}') ? ' has-error' : '' }}">
        <label class="col-sm-2 control-label">{$field['name']}</label>
        <div class="col-md-10">
            @foreach($options as \$option)
                <label>{!! Form::{$field['type']}('{$field['name']}', \$option) !!} \$option</label>
            @endforeach
            @if (\$errors->has('{$field['name']}'))
                <span class="help-block">
                    <strong>{{ \$errors->first('{$field['name']}') }}</strong>
                </span>
            @endif
        </div>
    </div>
HTML;

        return $html;
    }


    private function getOptionsAsString($array)
    {
        foreach ($array as &$item) {
            $item = "'{$item}'";
        }

        return '[' . implode(',', $array) . ']';
    }

    private function getCreateHtmlString()
    {
        $html     = '';
        $resource = $this->baseName();

        $html .= $this->getHeader();

        $html .= <<<HTML
        
            {!! Form::open(['route' => '{$resource}.store', 'method' => 'put',
			'class'=>'form-horizontal form-stripe pad-tb-15']) !!}
HTML;

        $html .= $this->getFormIncludeHtml($html);
        $html .= $this->getFooter();


        return $html;
    }

    private function getEditHtmlString()
    {
        $html     = '';
        $resource = $this->baseName();

        $html .= $this->getHeader();

        $html .= <<<HTML
        
            {!! Form::model(\${$resource}, ['route' => ['{$resource}.update', \${$resource}->id], 'method' => 'patch',
			'class'=>'form-horizontal form-stripe pad-tb-15']) !!}
HTML;

        $html .= $this->getFooter();

        return $html;
    }

    private function getHeader()
    {
        $html = <<<HTML
        @extends('layout.app')

        @section('content')
            <div class="container">
HTML;

        return $html;
    }

    private function getFooter()
    {
        $html = <<<HTML
        
            </div>
        @endSection
       
HTML;

        return $html;
    }

    private function baseName()
    {
        $resource = explode('\\', $this->name)[count(explode('\\', $this->name)) - 1];

        return $resource;
    }

    private function getRuleString($field)
    {
        if (in_array($field['type'], array_keys($this->validate))) {
            return "'{$field['name']}' => '{$this->validate[$field['type']]}', " . PHP_EOL;
        }

        return "'{$field['name']}' => '', " . PHP_EOL;
    }

    /**
     * @param $html
     *
     * @return string
     */
    private function getFormIncludeHtml($html)
    {
        $html = <<<HTML
                @include('{$$this->baseName()}.form')
            {!! Form::close() !!};
HTML;

        return $html;
    }
}