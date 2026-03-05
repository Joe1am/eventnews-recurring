<?php

declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\EventListener;

use TYPO3\CMS\Core\Configuration\Event\AfterFlexFormDataStructureParsedEvent;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * EventListener to add recurring settings to News FlexForms
 */
class AfterFlexFormDataStructureParsedEventListener
{
    public function __invoke(AfterFlexFormDataStructureParsedEvent $event): void
    {
        $dataStructure = $event->getDataStructure();
        $identifier = $event->getIdentifier();

        // Only for tt_content records with news plugins
        if ($identifier['type'] === 'tca' 
            && $identifier['tableName'] === 'tt_content' 
            && $this->isNewsPlugin($identifier['dataStructureKey'])
        ) {
            $flexFormPath = $this->getFlexFormPath();
            $content = file_get_contents($flexFormPath);
            
            if ($content) {
                $additionalFlexForm = GeneralUtility::xml2array($content);
                
                // Merge sheets from our FlexForm into the existing structure
                if (isset($additionalFlexForm['sheets']) && is_array($additionalFlexForm['sheets'])) {
                    foreach ($additionalFlexForm['sheets'] as $sheetKey => $sheetData) {
                        $dataStructure['sheets'][$sheetKey] = $sheetData;
                    }
                }
            }
        }
        
        $event->setDataStructure($dataStructure);
    }

    /**
     * Check if the dataStructureKey belongs to a news plugin
     */
    private function isNewsPlugin(string $dataStructureKey): bool
    {
        $validKeys = ['*,news_pi1', '*,news_newsliststicky', '*,news_newsselectedlist', '*,eventnews_'];
        
        foreach ($validKeys as $prefix) {
            if (str_starts_with($dataStructureKey, $prefix)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get path to FlexForm XML file
     */
    private function getFlexFormPath(): string
    {
        return ExtensionManagementUtility::extPath('eventnews_recurring') 
            . 'Configuration/FlexForms/flexform_recurring.xml';
    }
}
