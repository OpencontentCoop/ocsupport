<?php

use Opencontent\Opendata\Api\Exception\BaseException;
use Opencontent\Opendata\Api\Exception\ForbiddenException;

class OCSupportController extends ezpRestMvcController
{
    /**
     * @var ezpRestRequest
     */
    protected $request;

    private function checkAccess()
    {
        $access = eZUser::currentUser()->hasAccessTo('ocsupport', 'dashboard');
        if ($access['accessWord'] === 'no'){
            throw new ForbiddenException( 'support', 'read');
        }
    }

    private function doExceptionResult(Exception $exception)
    {
        $result = new ezcMvcResult;
        $result->variables['message'] = $exception->getMessage();

        $serverErrorCode = 500;
        $errorType = BaseException::cleanErrorCode(get_class($exception));

        if ($exception instanceof BaseException) {
            $serverErrorCode = $exception->getServerErrorCode();
            $errorType = $exception->getErrorType();
        }
        $result->status = new OcOpenDataErrorResponse(
            $serverErrorCode,
            $exception->getMessage(),
            $errorType
        );

        return $result;
    }

    public function doPackages()
    {
        try {
            $this->checkAccess();
            $result = new ezpRestMvcResult();
            $packages = OCSupportTools::getComposerPackages();
            $result->variables = $packages;
        } catch (Exception $e) {
            $result = $this->doExceptionResult($e);
        }
        return $result;
    }

    public function doVersion()
    {
        try {
            $this->checkAccess();
            $result = new ezpRestMvcResult();
            $installers = eZDB::instance()->arrayQuery("SELECT * FROM ezsite_data WHERE name like 'ocinstaller_%'");
            $result->variables = $installers;
        } catch (Exception $e) {
            $result = $this->doExceptionResult($e);
        }
        return $result;
    }

}
