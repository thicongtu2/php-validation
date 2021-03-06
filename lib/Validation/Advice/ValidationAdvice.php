<?php
declare(strict_type=1);


namespace Validation\Advice;


use ReflectionClass;
use Validation\BaseRequest;
use Validation\Common\StringUtils;
use Validation\Configuration;
use Validation\Exceptions\ValidatedClassNeedNonConstructorException;
use Validation\Exceptions\ValidationException;
use Validation\Http\Request;
use Validation\Type\StrongType;

class ValidationAdvice
{

    /**
     * @param BaseRequest $instance
     * @throws ValidationException
     * @throws \JsonMapper_Exception
     * @throws \ReflectionException
     * @throws ValidatedClassNeedNonConstructorException
     */
    public function advice(BaseRequest $instance){
        $validateStack = [];
        $annotationContainers = self::getAnnotationContainers($instance);
        $request = Request::init();
        foreach ($annotationContainers as $annotationContainer){
            $fieldName = $annotationContainer->fieldName;
            $strongType = $annotationContainer->strongType;
            // convention must be camel <=> snake
            $valueOfProperty = $request->get(StringUtils::camelToSnake($fieldName));

            // assign value
            if (Configuration::$greaterPHP74Version){
                // auto convert to strong type.
                if ($strongType->isBuildIn() && !$strongType->isArray()){
                    // build in is string, int, float, double
                    $valueOfProperty = StrongType::make($strongType, $valueOfProperty);
                }
            }else{
                // auto convert to strong type.
                $valueOfProperty = StrongType::make($strongType, $valueOfProperty);
            }
            try {
                $annotationContainer->validate($instance, $fieldName, $valueOfProperty);
            }catch (ValidationException $exception){
                $validateStack[$fieldName][] = $exception->getMessage();
            }
        }
        if (count($validateStack) > 0){
            throw new ValidationException(json_encode($validateStack));
        }
        return $instance;
    }

    /**
     * @param $instance BaseRequest|string
     * @return AnnotationContainer[]
     * @throws \ReflectionException
     */
    public static function getAnnotationContainers($instance){
        $reflect = new ReflectionClass($instance);
        $properties = $reflect->getProperties();
        if (is_object($instance)){
            $className = get_class($instance);
        }else{
            $className = $instance;
        }
        $instance->fieldList = [];
        $validationContainers = array();
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            if ($propertyName!='__fieldList'){
                $annotations = AnnotationReader::fromProperty($className, $propertyName);
                $strongType = StrongType::getStrongType($property);
                $validationContainer = new AnnotationContainer($propertyName, $annotations, $strongType, $property);
                $validationContainers[] = $validationContainer;
                $instance->fieldList[] = $propertyName;
            }
        }

        return $validationContainers;
    }
}
