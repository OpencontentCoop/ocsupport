<?php


class PackageChangeReader
{
    private $compareUrl;

    public function __construct($compareUrl)
    {
        $this->compareUrl = $compareUrl;
    }

    public function getChanges($excludePatterns = [])
    {
        if (strpos($this->compareUrl, 'github.com') !== false){
            return $this->getGithubChanges($excludePatterns);
        }

        //@todo
        return [];
    }

    private function getGithubChanges($excludePatterns = [])
    {
        $messages = [];
        $token = getenv('GITHUB_TOKEN');
        $apiCompareUrl = str_replace('https://github.com', 'https://api.github.com/repos', $this->compareUrl);
        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: PHP\r\nAuthorization: $token"
            ]
        ]);
        $compareResponse = @file_get_contents($apiCompareUrl, false, $context);
        if ($compareResponse){
            $compareData = json_decode($compareResponse, true);
            if(isset($compareData['commits'])){
                foreach ($compareData['commits'] as $commit) {
                    $message = $commit['commit']['message'];
                    $add = true;
                    foreach ($excludePatterns as $excludePattern){
                        if (strpos($message, $excludePattern) !== false){
                            $add = false;
                            break;
                        }
                    }
                    if ($add){
                        $messages[] = $message;
                    }
                }
            }
        }

        return $messages;
    }
}
