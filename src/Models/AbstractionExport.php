<?php

namespace WBTranslator\PluginLaravel\Models;

use WBTranslator as WBTranslatorSdk;

/**
 * Class AbstractionExport
 *
 * @package WBTranslator\PluginLaravel
 */
class AbstractionExport extends AbstractionBase
{
    public function abstractions(): WBTranslatorSdk\Collection
    {
        $collection = new WBTranslatorSdk\Collection;
    
        foreach ($this->langPaths() as $localeDirectory) {
            if (!file_exists($this->basePath . $localeDirectory)) {
                continue;
            }
            
            $rootGroup = $this->createGroup($localeDirectory);
    
            foreach ($this->filesystem->allFiles($this->basePath . $localeDirectory . $this->locale . DIRECTORY_SEPARATOR) as $file) {
                $relativePath = $file->getRelativePathname();
                $absolutePath = $localeDirectory . $this->locale . DIRECTORY_SEPARATOR . $relativePath;
                $data = $this->filesystem->getRequire($this->basePath . $absolutePath);
        
                if (file_exists($this->basePath . $absolutePath)) {
                    if (!empty($data) && is_array($data)) {
                        $group = $this->createGroup($relativePath, $rootGroup);
    
                        foreach ((self::arrayDot($data)) as $abstractName => $originalValue) {
                            if (!$abstractName) {
                                continue;
                            }
        
                            $translation = new WBTranslatorSdk\Translation;
                            $translation->setAbstractName($abstractName);
                            $translation->setOriginalValue(!empty($originalValue) ? (string)$originalValue : '');
                            $translation->addGroup($group);
                            
                            $collection->add($translation);
                        }
                    }
                }
            }
        }
 
        return $collection;
    }

    protected function createGroup(string $path, $parent = null)
    {
        $path = trim($path,DIRECTORY_SEPARATOR);
        $name = str_replace([DIRECTORY_SEPARATOR, '.php'], [$this->groupDelimiter, ''], $path);
        
        $group = new WBTranslatorSdk\Group();
        $group->setName($name);
        
        if (null !== $parent) {
            $group->addParent($parent);
        }
        
        return $group;
    }
}
