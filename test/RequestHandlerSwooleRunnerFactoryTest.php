<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
use Zend\Expressive\Swoole\Exception\InvalidConfigException;
use Zend\Expressive\Swoole\PidManager;
use Zend\Expressive\Swoole\RequestHandlerSwooleRunner;
use Zend\Expressive\Swoole\RequestHandlerSwooleRunnerFactory;
use Zend\Expressive\Swoole\ServerFactory;
use Zend\Expressive\Swoole\StdoutLogger;

class RequestHandlerSwooleRunnerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->applicationPipeline = $this->prophesize(ApplicationPipeline::class);
        $this->applicationPipeline->willImplement(RequestHandlerInterface::class);

        $this->serverRequest = $this->prophesize(ServerRequestInterface::class);

        $this->serverRequestError = $this->prophesize(ServerRequestErrorResponseGenerator::class);
        $this->serverFactory = $this->prophesize(ServerFactory::class);
        $this->pidManager = $this->prophesize(PidManager::class);

        $this->logger = $this->prophesize(LoggerInterface::class);

        $this->container = $this->prophesize(ContainerInterface::class);
        $this->container
            ->get(ApplicationPipeline::class)
            ->will([$this->applicationPipeline, 'reveal']);
        $this->container
            ->get(ServerRequestInterface::class)
            ->willReturn(function () {
                return $this->serverRequest->reveal();
            });
        $this->container
            ->get(ServerRequestErrorResponseGenerator::class)
            ->willReturn(function () {
                $this->serverRequestError->reveal();
            });
        $this->container
            ->get(ServerFactory::class)
            ->will([$this->serverFactory, 'reveal']);
        $this->container
            ->get(PidManager::class)
            ->will([$this->pidManager, 'reveal']);
        $this->container
            ->get('config')
            ->willReturn([]);
    }

    public function configureAbsentLoggerService()
    {
        $this->container
            ->has(LoggerInterface::class)
            ->willReturn(false);

        $this->container
            ->get(LoggerInterface::class)
            ->shouldNotBeCalled();
    }

    public function configureDocumentRoot()
    {
        $this->container
            ->get('config')
            ->willReturn([
                'zend-expressive-swoole' => [
                    'swoole-http-server' => [
                        'options' => [
                            'document_root' => __DIR__ . '/TestAsset',
                        ],
                    ],
                ],
            ]);
    }

    public function testInvocationWithoutLoggerServiceCreatesInstanceWithDefaultLogger()
    {
        $this->configureAbsentLoggerService();
        $this->configureDocumentRoot();
        $factory = new RequestHandlerSwooleRunnerFactory();
        $runner = $factory($this->container->reveal());
        $this->assertInstanceOf(RequestHandlerSwooleRunner::class, $runner);
        $this->assertAttributeInstanceOf(StdoutLogger::class, 'logger', $runner);
    }

    public function testInvocationWithoutDocumentRootResultsInException()
    {
        $this->configureAbsentLoggerService();
        $factory = new RequestHandlerSwooleRunnerFactory();
        $this->expectException(InvalidConfigException::class);
        $factory($this->container->reveal());
    }

    public function testFactoryWillUseConfiguredPsr3LoggerWhenPresent()
    {
        $this->configureDocumentRoot();
        $this->container
            ->has(LoggerInterface::class)
            ->willReturn(true);
        $this->container
            ->get(LoggerInterface::class)
            ->will([$this->logger, 'reveal']);

        $factory = new RequestHandlerSwooleRunnerFactory();
        $runner = $factory($this->container->reveal());
        $this->assertInstanceOf(RequestHandlerSwooleRunner::class, $runner);
        $this->assertAttributeSame($this->logger->reveal(), 'logger', $runner);
    }
}
