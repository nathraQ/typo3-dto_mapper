<?php
namespace Sudomake\DtoMapper\Mapper;

use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

class DtoMapper implements \TYPO3\CMS\Core\SingletonInterface{

    const OBJECT_STORAGE = 'TYPO3\CMS\Extbase\Persistence\ObjectStorage';

    /**
     * @var \TYPO3\CMS\Extbase\Reflection\ReflectionService
     * @inject
     */
    protected $reflectionService;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     * @inject
     */
    protected $objectManager;


    public function map($sourceObject, $targetClassName, $recursionDepth = 0) {
        $targetObject = $this->objectManager->get($targetClassName);
        $targetSchema = $this->reflectionService->getClassSchema($targetClassName);
        $sourceSchema = $this->reflectionService->getClassSchema($sourceObject);

        $targetProperties = $targetSchema->getProperties();
       foreach($targetProperties as $propertyName => $targetProperty) {
           if($sourceSchema->hasProperty($propertyName)) {
               $sourceProperty = $sourceSchema->getProperty($propertyName);
           } else {
               throw new \Sudomake\DtoMapper\Exception\MismatchingException();
           }
           $isSettable = ObjectAccess::isPropertySettable($targetObject, $propertyName);
           $isGettable = ObjectAccess::isPropertyGettable($sourceObject, $propertyName);

           // error is settable but not gettable this is a mismatch
           if($isSettable && !$isGettable) {
               throw new \Sudomake\DtoMapper\Exception\MismatchingException();
           }
           if($isSettable && $isGettable) {
               // storages
               if($targetProperty['type'] === DtoMapper::OBJECT_STORAGE && $sourceProperty['type'] === DtoMapper::OBJECT_STORAGE) {
                   if($targetProperty['elementType'] === $sourceProperty['elementType']) {
                       // if storage type of target and source are the same copy
                       $value = ObjectAccess::getProperty($sourceObject, $propertyName);
                       ObjectAccess::setProperty($targetObject, $propertyName, $value);
                   } else {
                       // else get source->get methodParameter type and target->set methodParameter type and call map +1
                       $subSourceObjects = ObjectAccess::getProperty($sourceObject, $propertyName);
                       $valueStorage = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();

                       foreach($subSourceObjects as $subSourceObject) {
                           $value = $this->map($subSourceObject, $targetProperty['elementType'], $recursionDepth + 1);
                           $valueStorage->attach($value);
                       }
                       ObjectAccess::setProperty($targetObject, $propertyName, $valueStorage);
                   }
               } else {
                   if($targetProperty['type'] === $sourceProperty['type']) {
                       // if annotation target and source are the same copy
                       $value = ObjectAccess::getProperty($sourceObject, $propertyName);
                       ObjectAccess::setProperty($targetObject, $propertyName, $value);
                   } else {
                       // else get source->get methodParameter type and target->set methodParameter type and call map +1
                       $subSourceObject = ObjectAccess::getProperty($sourceObject, $propertyName);
                       $value = $this->map($subSourceObject, $targetProperty['type'], $recursionDepth + 1);
                       ObjectAccess::setProperty($targetObject, $propertyName, $value);
                   }
               }
           }


           // not this way
//           $value = \TYPO3\CMS\Extbase\Reflection\ObjectAccess::getProperty($sourceObject, $property);


       }
       return $targetObject;
    }


} 