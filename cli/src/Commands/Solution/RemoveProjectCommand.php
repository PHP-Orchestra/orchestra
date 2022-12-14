<?php

namespace PhpOrchestra\Cli\Commands\Solution;

use PhpOrchestra\Application\Adapter\SolutionAdapterInterface;
use PhpOrchestra\Application\Facade\ProjectScannerInterface;
use PhpOrchestra\Application\Handler\CommandHandlerInterface;
use PhpOrchestra\Application\Handler\RemoveProjectFromSolutionHandlerInterface;
use PhpOrchestra\Domain\Defaults;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
        name: 'solution:remove-project',
        description: 'Removes a project from an existent solution.'
        )]
class RemoveProjectCommand extends Command
{
    protected static $defaultDescription = 'Removes a project from an existent solution.';
    private readonly RemoveProjectFromSolutionHandlerInterface $removeProjectHandler;
    private readonly SolutionAdapterInterface $solutionAdapter;
    private readonly ProjectScannerInterface $projectScanner;

    public function __construct(
        CommandHandlerInterface $commandHandler,
        SolutionAdapterInterface $solutionAdapter,
        ProjectScannerInterface $projectScanner
    ) {
        parent::__construct();
        $this->removeProjectHandler = $commandHandler;
        $this->solutionAdapter = $solutionAdapter;
        $this->projectScanner = $projectScanner;
    }

    protected function configure()
    {
        $this
            ->setHelp(self::$defaultDescription)
            ->addArgument(Defaults::ORCHESTRA_PROJECT_DIR, InputArgument::REQUIRED, 'The directory where your project is at.')
            ->addArgument(Defaults::ORCHESTRA_WORKING_DIR, InputArgument::OPTIONAL, 'The directory where your solution is at.', Defaults::ORCHESTRA_SOLUTION_WORKING_DIR_DEFAULT)
            ->addOption(Defaults::ORCHESTRA_DELETE_FILES, null, InputOption::VALUE_NONE, 'Force to remove the project from the disk?')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $workingDir = $input->getArgument(Defaults::ORCHESTRA_WORKING_DIR);
        $projectDir = $input->getArgument(Defaults::ORCHESTRA_PROJECT_DIR);
        $doDeleteFiles = $input->getOption(Defaults::ORCHESTRA_DELETE_FILES);
        $isDeleteFiles = false;

        try {
            if ($doDeleteFiles) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion('Removing a project is a destructive action. Do you want to proceed? (y/N)', false);

                if ($helper->ask($input, $output, $question)) {
                    $isDeleteFiles = true;
                }
            }

            $solution = $this->solutionAdapter->fetch($workingDir);
            $scannedProjects = $this->projectScanner->scan($projectDir);

            if (count($scannedProjects) != 1) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid project directory [%s]. Please provide the specific project directory.', $projectDir)
                );
            }

            $projectToRemove = reset($scannedProjects);

            $this->removeProjectHandler
            ->setSolution($solution)
            ->setProject($projectToRemove)
            ->doDeleteFiles($isDeleteFiles)
            ->handle();

            $output->writeln(sprintf('<info>The project [%s], was removed from the solution.</info>', $projectToRemove->getName()));

        } catch (\Exception $ex) {
            $output->writeln(
                    sprintf('<error>Failed to add project to the solution file. Error: %s</error>', $ex->getMessage())
            );
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}