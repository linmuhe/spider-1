<?php
require(__DIR__.'/../vendor/autoload.php');

use ddliu\spider\Spider;
use ddliu\spider\Pipe\NormalizeUrlPipe;
use ddliu\spider\Pipe\RequestPipe;
use ddliu\spider\Pipe\DomCrawlerPipe;

(new Spider())
    ->pipe(new NormalizeUrlPipe())
    ->pipe(new RequestPipe())
    ->pipe(new DomCrawlerPipe())
    ->pipe(function($spider, $task) {
	var_dump(array_keys($task->getData()));
        //$spider->logger->addInfo($task['url']."-pipe\n");
        if (!strpos($task['url'], 'tab=repositories')) return;
        $task['$dom']->filter('h3.repo-list-name>a')
            ->each(function($context) use ($task) {
		    $url = $context->attr('href');
		   
        $spider->logger->addInfo($url.' frok');
                $task->fork($url);
            });
    })
    ->pipe(function($spider, $task) {
        if (!preg_match('#^https://github.com/[\w_]+/[\w_]+$#', $task['url'])) return;
        $issueCount = trim($task['$dom']->filter('li.commits span.num')->text());
        $spider->logger->addInfo($task['url'].' has '.$issueCount.' commits');
    })
    ->addTask('https://github.com/ddliu?tab=repositories')
    ->run()
    ->report();
