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
 * Repeatable time-range FormEngine element.
 *
 * Each row contains a Von/Bis pair of native <input type="time"> elements.
 * Stores comma-separated HH:MM-HH:MM values.
 *
 * TCA config keys:
 *   labelAdd     string  LLL key for "Add" button
 *   labelRemove  string  LLL key for Remove button
 *   labelFrom    string  LLL key for "From" label
 *   labelTo      string  LLL key for "To" label
 */
class RepeatableTimeRangeElement extends AbstractFormElement
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

        $parameterArray = $this->data['parameterArray'];
        $config         = $parameterArray['fieldConf']['config'] ?? [];
        $fieldName      = $parameterArray['itemFormElName'];
        $currentValue   = trim((string)($parameterArray['itemFormElValue'] ?? ''));

        $labelAdd    = $lang->sL($config['labelAdd']    ?? '');
        $labelRemove = $lang->sL($config['labelRemove'] ?? '');
        $labelFrom   = $lang->sL($config['labelFrom']   ?? '');
        $labelTo     = $lang->sL($config['labelTo']     ?? '');

        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $iconPlus    = $iconFactory->getIcon('actions-plus', IconSize::SMALL)->render();
        $iconDelete  = $iconFactory->getIcon('actions-edit-delete', IconSize::SMALL)->render();

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
            $parts = explode('-', $item, 2);
            $from  = $parts[0] ?? '';
            $to    = $parts[1] ?? '';
            $rows .= $this->renderRow(
                htmlspecialchars($from),
                htmlspecialchars($to),
                $iconDelete,
                $labelFrom,
                $labelTo,
                $labelRemove,
                'r' . $key . $i++
            );
        }

        $templateRow = $this->renderRow('', '', $iconDelete, $labelFrom, $labelTo, $labelRemove, '__ENR_UID__');
        $labelAddEsc = htmlspecialchars($labelAdd);

        $result['html'] = $renderedLabel . <<<HTML
<div class="formengine-field-item t3js-formengine-field-item">
    {$fieldInformationHtml}
    <div class="enr-repeatable-timerange" data-enr-key="{$key}">
        <div class="enr-repeatable-timerange__list">{$rows}</div>
        <template class="enr-repeatable-timerange__row-template">{$templateRow}</template>
        <button type="button"
                class="btn btn-default enr-repeatable-timerange__add"
                style="margin-top:6px">
            {$iconPlus} {$labelAddEsc}
        </button>
        <input type="hidden"
               id="{$fieldId}"
               class="enr-repeatable-timerange__result"
               name="{$fieldName}"
               value="{$currentValue}" />
    </div>
</div>
HTML;

        $result['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            '@spielerj/eventnews-recurring/form-element/repeatable-timerange.js'
        );

        return $result;
    }

    private function renderRow(
        string $from,
        string $to,
        string $iconDelete,
        string $labelFrom,
        string $labelTo,
        string $labelRemove,
        string $uid
    ): string {
        $fromId         = 'formengine-input-enr-from-' . $uid;
        $toId           = 'formengine-input-enr-to-' . $uid;
        $labelFromEsc   = htmlspecialchars($labelFrom);
        $labelToEsc     = htmlspecialchars($labelTo);
        $labelRemoveEsc = htmlspecialchars($labelRemove);

        return <<<HTML
<div class="enr-repeatable-timerange__row" style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
    <label class="col-form-label text-nowrap" for="{$fromId}">{$labelFromEsc}</label>
    <input type="time"
           id="{$fromId}"
           class="form-control"
           data-enr-from="1"
           style="max-width:110px"
           value="{$from}" />
    <label class="col-form-label text-nowrap" for="{$toId}">{$labelToEsc}</label>
    <input type="time"
           id="{$toId}"
           class="form-control"
           data-enr-to="1"
           style="max-width:110px"
           value="{$to}" />
    <button type="button" class="btn btn-default enr-repeatable-timerange__remove"
            title="{$labelRemoveEsc}" aria-label="{$labelRemoveEsc}">
        {$iconDelete}
    </button>
</div>
HTML;
    }
}
