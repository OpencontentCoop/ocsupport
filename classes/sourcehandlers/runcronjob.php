<?php

class OCSupportRunCronJobSourceHandler extends SQLIImportAbstractHandler implements ISQLIImportHandler
{
    protected $cronPart = 'frequent';

    private $scriptDirectories;

    private $scripts = [];

    private $rowIndex = 0;

    private $rowCount = 0;

    public function initialize()
    {
        $ini = eZINI::instance('cronjob.ini');
        $scriptDirectories = $ini->variable('CronjobSettings', 'ScriptDirectories');
        $extensionDirectories = $ini->variable('CronjobSettings', 'ExtensionDirectories');
        $this->scriptDirectories = array_merge($scriptDirectories, eZExtension::expandedPathList($extensionDirectories, 'cronjobs'));

        if (isset($this->options['cron'])) {
            $this->cronPart = $this->options['cron'];
            $scriptGroup = "CronjobPart-$this->cronPart";
            $this->scripts = $ini->variable($scriptGroup, 'Scripts');
            $this->rowCount = count($this->scripts);
        }
    }

    public function getProcessLength()
    {
        return $this->rowCount;
    }

    public function getNextRow()
    {
        if ($this->rowIndex < $this->rowCount) {
            $row = $this->scripts[$this->rowIndex];
            $this->rowIndex++;
        } else {
            $row = false;
        }

        return $row;
    }

    public function process($row)
    {
        $scriptFile = false;
        $isQuiet = true;
        $cronPart = $row;
        $cli = eZCLI::instance();
        $cli->setIsQuiet(true);

        if ($cronPart == 'sqliimport_run.php' || $cronPart == 'clean_sqlitoken.php'){
            return false;
        }
        
        eZINI::instance()->setVariable('ContentSettings', 'ViewCaching', 'enabled');
        foreach ($this->scriptDirectories as $scriptDirectory) {
            $scriptFile = $scriptDirectory . '/' . $cronPart;
            if (file_exists($scriptFile))
                break;
        }
        if (file_exists($scriptFile)) {
            eZDebug::addTimingPoint("Script $scriptFile starting");
            eZRunCronjobs::runScript($this->cli, $scriptFile);
            eZDebug::addTimingPoint("Script $scriptFile done");
            // The transaction check
            $transactionCounterCheck = eZDB::checkTransactionCounter();
            if (isset($transactionCounterCheck['error'])) {
                eZDebug::writeError($transactionCounterCheck['error'], 'cron-' . $this->cronPart);
            }

            $this->progressionNotes .= $cronPart . '<br />';
        }
        eZINI::instance()->setVariable('ContentSettings', 'ViewCaching', 'disabled');
        
        return true;
    }

    public function cleanup()
    {
        return false;
    }

    public function getHandlerName()
    {
        return 'Run cronjob ' . $this->cronPart;
    }

    public function getHandlerIdentifier()
    {
        return 'runcronjobthandler';
    }

    public function getProgressionNotes()
    {
        return $this->progressionNotes;
    }

}
