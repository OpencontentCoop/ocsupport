<?php


class GitRepository extends \Cz\Git\GitRepository
{
    public function getLogs($range = null)
    {
        if (strpos($range, '..') === false){
            $range = '';
        }else{
            $ranges = explode('..', $range);
            if ($ranges[0] == 'first'){
                $ranges[0] = $this->getFirstCommitId();
            }
            if ($ranges[1] == 'last'){
                $ranges[1] = $this->getLastCommitId();
            }
            $range = implode('..', $ranges);
        }
        return $this->extractFromCommand('git log --pretty=format:%s ' . $range, 'trim');
    }

    public function getFirstCommitId()
    {
        $lines = $this->extractFromCommand('git log --pretty=format:\'%H\' --reverse');

        return $lines[0];
    }

    public function getTagsSortedByDate($excludes = [])
    {
        $tagDateList = $this->extractFromCommand("git tag -l --format='%(refname:strip=2)#%(taggerdate)#%(committerdate)'", 'trim');

        usort($tagDateList, function ($a, $b){
            $aParts = explode('#', $a);
            $bParts = explode('#', $b);
            $aDateString = empty($aParts[1]) ? $aParts[2] : $aParts[1];
            $bDateString = empty($bParts[1]) ? $bParts[2] : $bParts[1];
            $dateA = strtotime($aDateString);
            $dateB = strtotime($bDateString);
            return ($dateA < $dateB) ? -1 : 1;
        });

        $tagList = [];
        foreach ($tagDateList as $item){
            $tag = explode('#', $item)[0];
            if (!empty(trim($tag)) && !in_array($tag, $excludes)){
                $tagList[] = $tag;
            }
        }

        return $tagList;
    }

    public function getTagDate($tag, $asTimestamp = false)
    {
        $lines = $this->extractFromCommand('git log -1 --format=%ai ' . $tag);
        if (isset($lines[0])){
            if ($asTimestamp){
                return strtotime($lines[0]);
            }
            $parts = explode(' ', $lines[0]);

            return $parts[0];
        }

        return $asTimestamp ? 0 : null;
    }
}