<?php

require 'autoload.php';

use Aws\S3\S3Client as S3Client;
use Aws\S3\Exception\S3Exception;

abstract class AWSS3Abstract
{
    /** @var Aws\S3\S3Client */
    protected $s3client;

    /** @var string */
    protected $bucket;

    /** @var string */
    protected $httpHost;

    /** @var string */
    protected $protocol;

    protected function __construct(S3Client $s3client, $bucket, $httpHost, $protocol = 'https')
    {
        $this->s3client = $s3client;
        $this->bucket = $bucket;
        $this->httpHost = $httpHost;
        $this->protocol = $protocol;
    }

    /**
     * @return static
     */
    public static function build($parameters = [])
    {
        $region = $parameters['Region'];
        $bucket = $parameters['Bucket'];

        $args = [
            'region' => $region,
            'version' => 'latest',
        ];

        $httpHost = 's3-' . $region . '.amazonaws.com';

        if (isset($parameters['ServerUri'])) {
            $httpHost = $parameters['ServerUri'];
        }

        $protocol = isset($parameters['ServerProtocol']) ? $parameters['ServerProtocol'] : 'https';

        if (isset($parameters['Endpoint'])) {
            $args['endpoint'] = $parameters['Endpoint'];
        }

        if (isset($parameters['UsePathStyleEndpoint'])) {
            $args['use_path_style_endpoint'] = $parameters['UsePathStyleEndpoint'] === 'enabled';
        }

        $sdk = new Aws\Sdk($args);
        $client = $sdk->createS3();

        return new static($client, $bucket, $httpHost, $protocol);
    }

    /**
     * @return S3Client
     */
    public function getS3Client()
    {
        return $this->s3client;
    }

    public function getBucket()
    {
        return $this->bucket;
    }
}

class AWSS3Public extends AWSS3Abstract
{
    protected $acl = 'public-read';

    public function copyToDFS($srcFilePath, $dstFilePath = false)
    {
        try {
            $this->s3client->putObject(
                [
                    'Bucket' => $this->bucket,
                    'Key' => $dstFilePath ?: $srcFilePath,
                    'SourceFile' => $srcFilePath,
                    'ACL' => $this->acl,
                ]
            );
            return true;
        } catch (S3Exception $e) {
            eZDebug::writeError($e->getMessage(), __METHOD__);
            return false;
        }
    }

    public function getFile($srcFilePath)
    {
        try {
            $this->s3client->getObject(
                [
                    'Bucket' => $this->bucket,
                    'Key' => $srcFilePath,
                ]
            );
            return true;
        } catch (S3Exception $e) {
            return false;
        }
    }
}

class AWSS3Private extends AWSS3Public
{
    protected $acl = 'private';

    public function applyServerUri($filePath)
    {
        return $filePath;
    }
}

class DfsBackend extends eZDFSFileHandlerPostgresqlBackend
{
    private $mtimeFilter = '';

    /**
     * @param string $mtimeFilter
     */
    public function setMtimeFilter($mtimeFilter)
    {
        $this->mtimeFilter = $mtimeFilter;
    }

    public function getFileList($limit = false, $offest = false)
    {
        $table = $this->metaDataTable;
        $query = 'SELECT name FROM ' . $table . ' ' . $this->mtimeFilter . ' order by name asc';
        if ($limit !== false && $offest !== false) {
            $query .= " LIMIT {$limit} OFFSET {$offest}";
        }
        $stmt = $this->db->query($query);
        $filePathList = [];
        foreach ($stmt->fetchAll() as $row) {
            $filePathList[] = $row['name'];
        }

        unset($stmt);
        return $filePathList;
    }

    public function getFileCount()
    {
        $table = $this->metaDataTable;
        $query = 'SELECT count(*) FROM ' . $table . ' ' . $this->mtimeFilter;
        return $this->db->query($query)->fetchColumn();
    }
}

function pushToSlack($message)
{
    global $slackToken, $slackChannel;

    $result = false;
    if (!empty($slackToken) && !empty($slackChannel)){
        $ch = curl_init("https://slack.com/api/chat.postMessage");
        $data = http_build_query([
            "token" => $slackToken,
            "channel" => $slackChannel,
            "text" => $message, 
            "username" => "PusherBot",
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
    }
    return $result;
}

$script = eZScript::instance([
    'description' => ("Push in S3\n\n"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();

$options = $script->getOptions('[bucket:][key:][secret:][days:][slack-channel:][slack-token:]', '', []);
$script->initialize();
$script->setUseDebugAccumulators(true);
$cli = eZCLI::instance();
$startTime = new eZDateTime();
$user = eZUser::fetchByName('admin');
eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));

$slackToken = $options['slack-token'];
$slackChannel = $options['slack-channel'];

$limit = 100000;
$offset = 0;
$mtimeFilter = '';
if (!empty($options['days'])) {
    $days = (int)$options['days'];
    $daysInSeconds = $days * 86400;
    $now = time();
    $afterTime = $now - $daysInSeconds;
    $cli->output("Form " . date('Y-m-d h:i', $afterTime));
    $mtimeFilter = "WHERE mtime > $afterTime";
}

$verbose = $options['verbose'];
$dryRun = false;
$mountPointPath = eZINI::instance('file.ini')->variable('eZDFSClusteringSettings', 'MountPointPath');

if (!empty($options['key'])) {
    putenv("AWS_ACCESS_KEY_ID=" . $options['key']);
}
if (!empty($options['secret'])) {
    putenv("AWS_SECRET_ACCESS_KEY=" . $options['secret']);
}

$handlerParams = ['Region' => 'eu-west-1', 'Bucket' => !empty($options['bucket']) ? $options['bucket'] : 'undefined',];
$privateHandler = AWSS3Private::build($handlerParams);
$publicHandler = AWSS3Public::build($handlerParams);

function filePathForBinaryFile($fileName, $mimeType)
{
    $storageDir = eZSys::storageDirectory();
    list($group, $type) = explode('/', $mimeType);
    $filePath = $storageDir . '/original/' . $group . '/' . $fileName;
    return $filePath;
}

function pushFile($filePath)
{
    global $privateHandler, $publicHandler, $cli, $dryRun, $mountPointPath, $verbose;
    if (strpos($filePath, '/storage/images') === false) {
        $handler = $privateHandler;
        if ($verbose) {
            $cli->output(' (private) ', false);
        }
    } else {
        $handler = $publicHandler;
    }

    if (!file_exists($mountPointPath . '/' . $filePath)) {
        if ($verbose) {
            $cli->error('NOT FOUND');
        }
        return;
    }
    if ($dryRun) {
        if ($verbose) {
            $cli->output('@', false);
        }
    } else {
        if (!$handler->getFile($filePath)) {
            $handler->copyToDFS($mountPointPath . '/' . $filePath, $filePath);
            if ($verbose) {
                $cli->warning('*', false);
            }
        } else {
            if ($verbose) {
                $cli->output('+', false);
            }
        }
    }
    if ($verbose) {
        $cli->output();
    }
}

$cli->warning($limit . ' ' . $offset);

try {
    $db = new DfsBackend();
    $db->setMtimeFilter($mtimeFilter);
    $db->_connect();
    $fileCount = $db->getFileCount();
    $cli->output("Migrating $fileCount files");

    if (!$verbose) {
        $output = new ezcConsoleOutput();
        $progressBarOptions = ['emptyChar' => ' ', 'barChar' => '='];
        $progressBar = new ezcConsoleProgressbar($output, $fileCount, $progressBarOptions);
        $progressBar->start();
    }

    while ($fileList = $db->getFileList($limit, $offset)) {
        if ($verbose) {
            $cli->warning($limit . ' ' . $offset);
            foreach ($fileList as $index => $file) {
                $index++;
                $cli->output("$index/$fileCount $file ", false);
                pushFile($file);
            }
        } else {
            foreach ($fileList as $file) {
                pushFile($file);
                $progressBar->advance();
            }
        }
        $offset += $limit;
    }

    if (!$verbose) {
        $progressBar->finish();
        $cli->output();
    }
} catch (Exception $e) {
    $cli->error($e->getMessage());
}

$endTime = new eZDateTime();
$elapsedTime = new eZTime($endTime->timeStamp() - $startTime->timeStamp());
$elapsedTimeMsg = 'Elapsed time: ' . sprintf('%02d:%02d:%02d', $elapsedTime->hour(), $elapsedTime->minute(), $elapsedTime->second());
$cli->output($elapsedTimeMsg);
$identifier = OpenPABase::getCurrentSiteaccessIdentifier();
pushToSlack( "[{$identifier}] Sync to S3 done. " . $elapsedTimeMsg);

$script->shutdown();