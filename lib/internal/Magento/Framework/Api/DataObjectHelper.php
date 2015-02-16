<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Framework\Api;

class DataObjectHelper
{
    /**
     * @var ObjectFactory
     */
    protected $objectFactory;

    /**
     * @var \Magento\Framework\Reflection\DataObjectProcessor
     */
    protected $objectProcessor;

    /**
     * @var \Magento\Framework\Reflection\TypeProcessor
     */
    protected $typeProcessor;

    /**
     * @param ObjectFactory $objectFactory
     * @param \Magento\Framework\Reflection\DataObjectProcessor $objectProcessor
     * @param \Magento\Framework\Reflection\TypeProcessor $typeProcessor
     */
    public function __construct(
        ObjectFactory $objectFactory,
        \Magento\Framework\Reflection\DataObjectProcessor $objectProcessor,
        \Magento\Framework\Reflection\TypeProcessor $typeProcessor
    ) {
        $this->objectFactory = $objectFactory;
        $this->objectProcessor = $objectProcessor;
        $this->typeProcessor = $typeProcessor;
    }

    /**
     * @param ExtensibleDataInterface $dataObject
     * @param array $data
     * @param string $interfaceName
     * @return $this
     */
    public function populateWithArray(ExtensibleDataInterface $dataObject, array $data, $interfaceName = null)
    {
        $this->_setDataValues($dataObject, $data, $interfaceName);
        return $this;
    }

    /**
     * Update Data Object with the data from array
     *
     * @param ExtensibleDataInterface $dataObject
     * @param array $data
     * @param string $interfaceName
     * @return $this
     */
    protected function _setDataValues(ExtensibleDataInterface $dataObject, array $data, $interfaceName)
    {
        $dataObjectMethods = get_class_methods(get_class($dataObject));
        foreach ($data as $key => $value) {
            /* First, verify is there any setter for the key on the Service Data Object */
            $camelCaseKey = \Magento\Framework\Api\SimpleDataObjectConverter::snakeCaseToUpperCamelCase($key);
            $possibleMethods = [
                'set' . $camelCaseKey,
                'setIs' . $camelCaseKey,
            ];
            if ($key === ExtensibleDataInterface::CUSTOM_ATTRIBUTES
                && is_array($data[$key])
                && !empty($data[$key])
            ) {
                foreach ($data[$key] as $customAttribute) {
                    $dataObject->setCustomAttribute(
                        $customAttribute[AttributeInterface::ATTRIBUTE_CODE],
                        $customAttribute[AttributeInterface::VALUE]
                    );
                }
            } elseif ($methodNames = array_intersect($possibleMethods, $dataObjectMethods)) {
                $methodName = array_values($methodNames)[0];
                if (!is_array($value)) {
                    $dataObject->$methodName($value);
                } else {
                    $getterMethodName = 'get' . $camelCaseKey;
                    $this->setComplexValue($dataObject, $getterMethodName, $methodName, $value, $interfaceName);
                }
            } else {
                $dataObject->setCustomAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * @param ExtensibleDataInterface $dataObject
     * @param string $getterMethodName
     * @param string $methodName
     * @param array $value
     * @param string $interfaceName
     * @return $this
     */
    protected function setComplexValue(
        ExtensibleDataInterface $dataObject,
        $getterMethodName,
        $methodName,
        array $value,
        $interfaceName
    ) {
        if ($interfaceName == null) {
            $interfaceName = get_class($dataObject);
        }
        $returnType = $this->objectProcessor->getMethodReturnType($interfaceName, $getterMethodName);
        if ($this->typeProcessor->isTypeSimple($returnType)) {
            $dataObject->$methodName($value);
            return $this;
        }

        if ($this->typeProcessor->isArrayType($returnType)) {
            $type = $this->typeProcessor->getArrayItemType($returnType);
            $objects = [];
            foreach ($value as $arrayElementData) {
                $object = $this->objectFactory->create($type, []);
                $this->populateWithArray($object, $arrayElementData, $type);
                $objects[] = $object;
            }
            $dataObject->$methodName($objects);
            return $this;
        }

        if (is_subclass_of($returnType, '\Magento\Framework\Api\ExtensibleDataInterface')) {
            $object = $this->objectFactory->create($returnType, []);
            $this->populateWithArray($object, $value, $returnType);
        } else {
            $object = $this->objectFactory->create($returnType, $value);
        }
        $dataObject->$methodName($object);
        return $this;
    }

    /**
     * Merges second object onto the first
     *
     * @param string                  $interfaceName
     * @param ExtensibleDataInterface $firstDataObject
     * @param ExtensibleDataInterface $secondDataObject
     * @return $this
     * @throws \LogicException
     */
    public function mergeDataObjects(
        $interfaceName,
        ExtensibleDataInterface $firstDataObject,
        ExtensibleDataInterface $secondDataObject
    ) {
        if (!$firstDataObject instanceof $interfaceName || !$secondDataObject instanceof $interfaceName) {
            throw new \LogicException('Wrong prototype object given. It can only be of "' . $interfaceName . '" type.');
        }
        $secondObjectArray = $this->objectProcessor->buildOutputDataArray($secondDataObject, $interfaceName);
        $this->_setDataValues($firstDataObject, $secondObjectArray, $interfaceName);
        return $this;
    }
}
