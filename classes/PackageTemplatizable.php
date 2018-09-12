<?php

class PackageTemplatizable
{
    private $package;

    private $attributes;

    public function __construct(ComposerLockParser\Package $package)
    {
        $this->package = $package;
    }

    public function attributes()
    {
        if ($this->attributes === null) {
            $this->attributes = array();
            try {
                $reflect = new ReflectionClass($this->package);
                $properties = $reflect->getProperties(ReflectionProperty::IS_PRIVATE);
                foreach ($properties as $property) {
                    $this->attributes[] = $property->getName();
                }

            } catch (Exception $e) {
                eZDebug::writeError($e->getMessage(), __FILE__);
            }
        }

        return $this->attributes;
    }

    public function hasAttribute($key)
    {
        return in_array($key, $this->attributes());
    }

    public function attribute($key)
    {
        $method = 'get' . ucfirst($key);
        if (method_exists($this->package, $method)) {
            return $this->package->{$method}();
        }

        eZDebug::writeNotice("Attribute $key does not exist", get_called_class());

        return false;
    }
}
