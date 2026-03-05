<?php

declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Repeatable date-picker FormEngine element.
 *
 * Each row contains one TYPO3 flatpickr date input.
 * Stores comma-separated Y-m-d values.
 *
 * TCA config keys:
 *   labelAdd        string  LLL key for "Add" button
 *   labelRemove     string  LLL key for Remove button
 *   labelOpenPicker string  LLL key for calendar icon button aria-label
 */
class RepeatableDateElement extends AbstractFormElement
{
    protected $defaultFieldInformation = [
        'tcaDescription' => [
            'renderType' => 'tcaDescription',
        ],
    ];

    public function render(): array
    {
        $result = $this->initializeResultArray();
        $lang   = $this->getLanguageService();

        $parameterArray  = $this->data['parameterArray'];
        $config          = $parameterArray['fieldConf']['config'] ?? [];
        $fieldName       = $parameterArray['itemFormElName'];
        $currentValue    = trim((string)($parameterArray['itemFormElValue'] ?? ''));

        $labelAdd        = $lang->sL($config['labelAdd']        ?? '');
        $labelRemove     = $lang->sL($config['labelRemove']     ?? '');
        $labelOpenPicker = $lang->sL($config['labelOpenPicker'] ?? '');

        $iconFactory  = GeneralUtility::makeInstance(IconFactory::class);
        $iconPlus     = $iconFactory->getIcon('actions-plus', IconSize::SMALL)->render();
        $iconDelete   = $iconFactory->getIcon('actions-edit-delete', IconSize::SMALL)->render();
        $iconCalendar = $iconFactory->getIcon('actions-edit-pick-date', IconSize::SMALL)->render();

        $key           = md5($fieldName);
        $fieldId       = StringUtility::getUniqueId('formengine-input-enr-');
        $renderedLabel = $this->renderLabel($fieldId);

        $fieldInformationResult = $this->renderFieldInformation();
        $fieldInformationHtml   = $fieldInformationResult['html'];
        $result = $this->mergeChildReturnIntoExistingResult($result, $fieldInformationResult, false);

        $items = $currentValue !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $currentValue))))
            : [];

        $rows = '';
        $i    = 0;
        foreach ($items as $item) {
            $rows .= $this->renderRow(
                htmlspecialchars($item),
                $iconDelete,
                $iconCalendar,
                $labelOpenPicker,
                $labelRemove,
                'r' . $key . $i++
            );
        }

        $templateRow = $this->renderRow('', $iconDelete, $iconCalendar, $labelOpenPicker, $labelRemove, '__ENR_UID__');
        $labelAddEsc = htmlspecialchars($labelAdd);

        $result['html'] = $renderedLabel . <<<HTML
<div class="formengine-field-item t3js-formengine-field-item">
    {$fieldInformationHtml}
    <div class="enr-repeatable-date" data-enr-key="{$key}">
        <div class="enr-repeatable-date__list">{$rows}</div>
        <template class="enr-repeatable-date__row-template">{$templateRow}</template>
        <button type="button"
                class="btn btn-default enr-repeatable-date__add"
                style="margin-top:6px">
            {$iconPlus} {$labelAddEsc}
        </button>
        <input type="hidden"
               id="{$fieldId}"
               class="enr-repeatable-date__result"
               name="{$fieldName}"
               value="{$currentValue}" />
    </div>
</div>
HTML;

        $result['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            '@spielerj/eventnews-recurring/form-element/repeatable-date.js'
        );

        return $result;
    }

    private function renderRow(
        string $date,
        string $iconDelete,
        string $iconCalendar,
        string $labelOpenPicker,
        string $labelRemove,
        string $uid
    ): string {
        $inputId            = 'formengine-input-enr-' . $uid;
        $isoValue           = $date !== '' ? $date . 'T00:00:00Z' : '';
        $labelOpenPickerEsc = htmlspecialchars($labelOpenPicker);
        $labelRemoveEsc     = htmlspecialchars($labelRemove);

        return <<<HTML
<div class="enr-repeatable-date__row" style="display:flex;align-items:stretch;gap:6px;margin-bottom:4px">
    <div class="form-control-wrap" style="max-width:204px">
        <div class="input-group">
            <input type="text"
                   id="{$inputId}"
                   class="form-control enr-repeatable-date__dt-input"
                   data-input-type="datetimepicker"
                   data-date-type="date"
                   data-enr-value-source="1"
                   autocomplete="off"
                   value="{$isoValue}" />
            <button class="btn btn-default" type="button" tabindex="-1"
                    aria-label="{$labelOpenPickerEsc}"
                    data-enr-open-picker="{$inputId}">
                {$iconCalendar}
            </button>
        </div>
    </div>
    <button type="button" class="btn btn-default enr-repeatable-date__remove"
            title="{$labelRemoveEsc}" aria-label="{$labelRemoveEsc}">
        {$iconDelete}
    </button>
</div>
HTML;
    }
}
