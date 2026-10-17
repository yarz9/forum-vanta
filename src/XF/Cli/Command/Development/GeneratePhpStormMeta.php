<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;
use XF\ControllerPlugin\AbstractPlugin;
use XF\Mvc\Entity\Finder;
use XF\Searcher\AbstractSearcher;
use XF\Service\AbstractService;
use XF\Util\File;
use XF\Validator\AbstractValidator;

class GeneratePhpStormMeta extends AbstractCommand
{
	use RequiresDevModeTrait;

	protected function configure()
	{
		$this
			->setName('xf-dev:generate-phpstorm-meta')
			->setDescription('Generates a .phpstorm.meta.php file for dynamic return type hinting')
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'Path to save the generated .phpstorm.meta.php file.',
				'.phpstorm.meta.php'
			)
			->addOption(
				'print',
				null,
				InputOption::VALUE_NONE,
				'If enabled, prints instead of writing'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$staticMethodTypes = '';
		$definitions = $this->getMetaDefinitions();
		$searchPaths = $this->getSearchPaths();

		foreach ($definitions AS $type => $definition)
		{
			$classes = '';

			foreach ($searchPaths AS $addOnId => $filePath)
			{
				$searchDir = sprintf(
					str_replace('\\', \XF::$DS, $definition['format']),
					$filePath,
					''
				);
				if (!file_exists($searchDir) || !is_dir($searchDir))
				{
					continue;
				}

				$iterator = new \RegexIterator(
					File::getRecursiveDirectoryIterator($searchDir, null, null),
					'/\.php$/'
				);

				/** @var \SplFileInfo $file */
				foreach ($iterator AS $name => $file)
				{
					$suffixClass = str_replace($searchDir, '', $file->getRealPath());
					$suffixClass = str_replace('.php', '', $suffixClass);
					$suffixClass = trim(str_replace(\XF::$DS, '\\', $suffixClass), '\\');
					$fqClass = sprintf($definition['format'], "\\{$addOnId}", $suffixClass);

					if (!class_exists($fqClass))
					{
						continue;
					}

					$reflection = new \ReflectionClass($fqClass);
					if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait())
					{
						continue;
					}

					$shortClass = "{$addOnId}:{$suffixClass}";
					$classes .= ($classes ? ",\n" : "\n") . "\t\t'{$shortClass}' => '{$fqClass}'";

					$defaultPrefix = $definition['defaultPrefix'] ?? '';
					if ($defaultPrefix !== '' && $addOnId === $defaultPrefix)
					{
						$classes .= ($classes ? ",\n" : "\n") . "\t\t'{$suffixClass}' => '{$fqClass}'";
					}
				}
			}

			$staticMethodTypes .= "\t// $type\n";
			foreach ($definition['methods'] AS $method)
			{
				$staticMethodTypes .= <<< STATICMETHODTYPES
\toverride($method, map([$classes\n\t]));\n
STATICMETHODTYPES;

			}
		}

		$staticMethodTypes = rtrim($staticMethodTypes, ",\n");

		$fileOutput = <<< METAOUT
<?php

namespace PHPSTORM_META
{
{$staticMethodTypes}
}
METAOUT;

		if ($input->getOption('print'))
		{
			$output->writeln($fileOutput);
		}
		else
		{
			$path = $input->getArgument('path');
			$dir = dirname($path);

			$output->writeln("Writing $path...");

			if (!is_writable($dir))
			{
				$output->writeln("File could not be written to. Check directories exist and permissions.");
				return 5;
			}

			file_put_contents($path, $fileOutput);
			$output->writeln("Written successfully.");
		}

		return 0;
	}

	protected function getMetaDefinitions()
	{
		return [
			'AddOnDataType' => [
				'format' => '%s\AddOn\DataType\%s',
				'methods' => [
					'\XF\AddOn\DataManager::getDataTypeHandler(0)',
				],
			],
			'Auth' => [
				'format' => '%s\Authentication\%s',
				'methods' => [
					'\XF\App::auth(0)',
				],
			],
			'Behavior' => [
				'format' => '%s\Behavior\%s',
				'methods' => [
					'\XF\Mvc\Entity::getBehavior(0)',
				],
			],
			'Captcha' => [
				'format' => '%s\Captcha\%s',
				'methods' => [
					'\XF\App::captcha(0)',
				],
				'defaultPrefix' => 'XF',
			],
			'ConnectedAccount' => [
				'format' => '%s\ConnectedAccount\%s',
				'methods' => [
					'\XF\SubContainer\OAuth::provider(0)',
					'\XF\SubContainer\OAuth::providerData(0)',
				],
			],
			'ControllerPlugin' => [
				'format' => '%s\ControllerPlugin\%s',
				'methods' => [
					'\XF\Mvc\Controller::plugin(0)',
				],
				'default' => AbstractPlugin::class,
			],
			'Criteria' => [
				'format' => '%s\Criteria\%s',
				'methods' => [
					'\XF\App::criteria(0)',
				],
			],
			'Data' => [
				'format' => '%s\Data\%s',
				'methods' => [
					'\XF\App::data(0)',
					'\XF\Mvc\Controller::data(0)',
				],
			],
			'DesignerOutput' => [
				'format' => '%s\DesignerOutput\%s',
				'methods' => [
					'\XF\DesignerOutput::getHandler(0)',
				],
			],
			'DevelopmentOutput' => [
				'format' => '%s\DevelopmentOutput\%s',
				'methods' => [
					'\XF\DevelopmentOutput::getHandler(0)',
				],
			],
			'Entity' => [
				'format' => '%s\Entity\%s',
				'methods' => [
					'\XF\ActivitySummary\AbstractSection::findOne(0)',
					'\XF\AddOn\DataType\AbstractDataType::create(0)',
					'\XF\App::find(0)',
					'\XF\Mvc\Controller::assertRecordExists(0)',
					'\XF\Mvc\Controller::assertRecordExistsByUnique(0)',
					'\XF\Mvc\Controller::assertViewableRecord(0)',
					'\XF\Mvc\Entity\Manager::create(0)',
					'\XF\Mvc\Entity\Manager::find(0)',
					'\XF\Mvc\Entity\Manager::findCached(0)',
					'\XF\Mvc\Entity\Manager::findOne(0)',
					'\XF\Service\AbstractService::findOne(0)',
					'\XF\Widget\AbstractWidget::findOne(0)',
				],
			],
			'Filterer' => [
				'format' => '%s\Filterer\%s',
				'methods' => [
					'\XF\App::filter(0)',
				],
			],
			'Finder' => [
				'format' => '%s\Finder\%s',
				'methods' => [
					'\XF::finder(0)',
					'\XF\ActivitySummary\AbstractSection::finder(0)',
					'\XF\AddOn\DataType\AbstractDataType::finder(0)',
					'\XF\App::finder(0)',
					'\XF\Mvc\Controller::finder(0)',
					'\XF\Mvc\Entity\Entity::finder(0)',
					'\XF\Mvc\Entity\Manager::getFinder(0)',
					'\XF\Mvc\Entity\Repository::finder(0)',
					'\XF\Service\AbstractService::finder(0)',
					'\XF\Widget\AbstractWidget::finder(0)',
				],
				'default' => Finder::class,
			],
			'Helper' => [
				'format' => '%s\Helper\%s',
				'methods' => [
					'\XF::helper(0)',
					'\XF\App::helper(0)',
					'\XF\Mvc\Controller::helper(0)',
				],
			],
			'ImportData' => [
				'format' => '%s\Import\Data\%s',
				'methods' => [
					'\XF\Import\DataManager::newHandler(0)',
				],
			],
			'ImportDataHelper' => [
				'format' => '%s\Import\DataHelper\%s',
				'methods' => [
					'\XF\Import\DataManager::helper(0)',
				],
			],
			'InlineMod' => [
				'format' => '%s\InlineMod\%s',
				'methods' => [
					'\XF\InlineMod\AbstractHandler::getActionHandler(0)',
				],
			],
			'Job' => [
				'format' => '%s\Job\%s',
				'methods' => [
					'\XF\App::job(0)',
				],
			],
			'Notifier' => [
				'format' => '%s\Notifier\%s',
				'methods' => [
					'\XF\App::notifier(0)',
				],
			],
			'PreRegAction' => [
				'format' => '%s\PreRegAction\%s',
				'methods' => [
					'\XF\Repository\PreRegAction::getActionHandler(0)',
				],
			],
			'Repository' => [
				'format' => '%s\Repository\%s',
				'methods' => [
					'\XF::repository(0)',
					'\XF\ActivitySummary\AbstractSection::repository(0)',
					'\XF\App::repository(0)',
					'\XF\Import\Data\AbstractData::repository(0)',
					'\XF\Mvc\Controller::repository(0)',
					'\XF\Mvc\Entity\Behavior::repository(0)',
					'\XF\Mvc\Entity\Entity::repository(0)',
					'\XF\Mvc\Entity\Manager::getRepository(0)',
					'\XF\Mvc\Entity\Repository::repository(0)',
					'\XF\Service\AbstractService::repository(0)',
					'\XF\Widget\AbstractWidget::repository(0)',
				],
			],
			'Searcher' => [
				'format' => '%s\Searcher\%s',
				'methods' => [
					'\XF\App::searcher(0)',
					'\XF\Mvc\Controller::searcher(0)',
				],
				'default' => AbstractSearcher::class,
			],
			'Service' => [
				'format' => '%s\Service\%s',
				'methods' => [
					'\XF::service(0)',
					'\XF\ActivitySummary\AbstractSection::service(0)',
					'\XF\App::service(0)',
					'\XF\Mvc\Controller::service(0)',
					'\XF\Service\AbstractService::service(0)',
					'\XF\Widget\AbstractWidget::service(0)',
				],
				'default' => AbstractService::class,
			],
			'Validator' => [
				'format' => '%s\Validator\%s',
				'methods' => [
					'\XF\App::validator(0)',
				],
				'default' => AbstractValidator::class,
				'defaultPrefix' => 'XF',
			],
		];
	}

	protected function getSearchPaths()
	{
		$paths = [
			'XF' => \XF::getSourceDirectory() . \XF::$DS . 'XF',
		];
		$addOnManager = \XF::app()->addOnManager();

		foreach ($addOnManager->getAvailableAddOnIds() AS $addOnId)
		{
			if ($addOnId == 'XF')
			{
				continue;
			}

			$paths[str_replace('/', '\\', $addOnId)] = $addOnManager->getAddOnPath($addOnId);
		}

		return $paths;
	}
}
