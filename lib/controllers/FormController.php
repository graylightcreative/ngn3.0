<?php

// Function for a form group with floating label and input/textarea/select
function createFloatingFormGroup($label, $elementType, $name, $value = '', $options = [], $required = false, $size='') {
	$inputField = '';
	$placeholder = $name;

	$requiredAttribute = $required ? 'required' : '';

    switch($size){
        case "lg":
            $formControl = ' form-control-lg';
            break;
        case "sm":
            $formControl = ' form-control-sm';
            break;
        default:
            $formControl = '';
            break;
    }

	switch ($elementType) {
		case 'text':
		case 'number':
		case 'email':
		case 'password':
		case 'date':
        case 'time':
			$type = $elementType;
			$inputField = "<input type='$type' class='form-control$formControl' id='$name' name='$name' placeholder='$placeholder' value='$value' $requiredAttribute>"; // Add $requiredAttribute
			break;
		case 'textarea':
			$inputField = "<textarea class='form-control$formControl' id='$name' name='$name' placeholder='$placeholder' $requiredAttribute>$value</textarea>"; // Add $requiredAttribute
			break;
		case 'select':
			$inputField = "<select class='form-select form-control$formControl' id='$name' name='$name' aria-label='$label' $requiredAttribute>"; // Add $requiredAttribute
			foreach ($options as $optionValue => $optionText) {
				$selected = ($optionValue == $value) ? 'selected' : '';
				$inputField .= "<option value='$optionValue' $selected>$optionText</option>";
			}
			$inputField .= '</select>';
			break;
	}

	return <<<HTML
    <div class="form-floating mb-3">
        $inputField
        <label for="$name">$label</label>
    </div>
HTML;
}

// Function for checkboxes or radio buttons (generates a group)
function createOptionsGroup($label, $type, $name, $options, $checkedValues = [], $inline = false)
{
	$inputFields = '';
	$class = $inline ? 'form-check-inline' : 'form-check';

	foreach ($options as $optionValue => $optionText) {
		$checked = in_array($optionValue, $checkedValues) ? 'checked' : '';
		$inputFields .= <<<HTML
        <div class="$class">
            <input class="form-check-input" type="$type" id="{$name}_{$optionValue}" name="$name" value="$optionValue" $checked>
            <label class="form-check-label" for="{$name}_{$optionValue}">
                $optionText
            </label>
        </div>
HTML;
	}

	return <<<HTML
    <div class="mb-3">
        <label>$label</label>
        $inputFields
    </div>
HTML;
}

// Function for toggle switch
function createToggleSwitch($label, $name, $checked = false) {
	$checkedAttr = $checked ? ' checked' : '';
	return <<<HTML
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="$name" name="$name" value=1$checkedAttr>
        <label class="form-check-label" for="$name">$label</label>
    </div>
HTML;
}
function createToggleButton($label, $name, $value, $checked = false) {
	$checkedAttr = $checked ? ' checked' : '';
	return <<<HTML
    <input type="checkbox" class="btn-check" id="$name" autocomplete="off" name="$name" value="$value">
<label class="btn btn-outline-primary no-loading" for="$name">$label</label>
HTML;
}

// Function for a range input
function createRangeInput($label, $name, $min = 0, $max = 100, $value = 50) {
	return <<<HTML
    <div class="mb-3">
        <label for="$name" class="form-label">$label</label>
        <input type="range" class="form-range" id="$name" name="$name" min="$min" max="$max" value="$value">
    </div>
HTML;
}
// Function for custom file input with floating label
function createFloatingFileInput($label, $name, $multiple = false, $required = false)
{
	$multipleAttr = $multiple ? 'multiple' : '';
	$requiredAttr = $required ? 'required' : '';
	return <<<HTML
    <div class="form-floating mb-3">
        <input type="file" class="form-control" id="$name" name="$name" placeholder="" $multipleAttr $requiredAttr>
        <label for="$name">$label</label>
    </div>
HTML;
}

// Function for custom textarea with floating label
function createFloatingTextarea($label, $name, $value = '', $rows = 3, $required = false) {
	$requiredAttr = $required ? 'required' : '';
	return <<<HTML
    <div class="form-floating mb-3">
        <textarea class="form-control" placeholder="$label" id="$name" name="$name" style="height: auto;" rows="$rows" $requiredAttr>$value</textarea>
        <label for="$name">$label</label>
    </div>
HTML;
}


// Function for a color input
function createColorInput($label, $name, $value = '#007bff') {
	return <<<HTML
    <div class="mb-3">
        <label for="$name" class="form-label">$label</label>
        <input type="color" class="form-control form-control-color" id="$name" name="$name" value="$value">
    </div>
HTML;
}

// Function for a date input
function createDateInput($label, $name, $value = '', $required = false) {
	$requiredAttr = $required ? 'required' : '';
	return <<<HTML
    <div class="form-floating mb-3">
        <input type="date" class="form-control" id="$name" name="$name" value="$value" $requiredAttr>
        <label for="$name">$label</label>
    </div>
HTML;
}

//
//// Usage Examples (all form components)
//
//echo createFloatingFormGroup('Name', 'text', 'name', 'John Doe', [], true); // Required
//echo createFloatingFormGroup('Email', 'email', 'email', '', [], true); // Required
//echo createFloatingFormGroup('Password', 'password', 'password');
//echo createFloatingTextarea('Message', 'message', 'This is a sample message', 5);
//echo createFloatingFormGroup('Country', 'select', 'country', 'CA', $countries);
//
//echo createOptionsGroup('Favorite Colors', 'checkbox', 'colors', $colors, ['red', 'blue']);
//echo createOptionsGroup('Gender', 'radio', 'gender', ['M' => 'Male', 'F' => 'Female'], ['M']);
//echo createToggleSwitch('Enable Notifications', 'notifications', true);
//echo createRangeInput('Volume', 'volume');
//
//// File and color inputs don't use floating labels
//echo createFloatingFileInput('Profile Picture', 'profile_pic');
//echo createColorInput('Favorite Color', 'fav_color');
//echo createDateInput('Birthdate', 'birthdate');