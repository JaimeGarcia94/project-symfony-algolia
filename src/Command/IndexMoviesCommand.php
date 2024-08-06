<?php

namespace App\Command;

use App\Entity\Movie;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\SearchService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use DateTime;

class IndexMoviesCommand extends Command
{
    protected static $defaultName = 'app:index-movies';

    private $entityManager;
    private $searchService;

    public function __construct(EntityManagerInterface $entityManager, SearchService $searchService)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->searchService = $searchService;
    }

    protected function configure()
    {
        $this
            ->setName(self::$defaultName) // Asegura que el nombre del comando esté configurado
            ->setDescription('Index movies from a CSV file into Algolia');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Indexing Movies to Algolia');

        $csvFile = __DIR__ . '/../../data/movies.csv';
        if (!file_exists($csvFile)) {
            $io->error('CSV file not found');
            return Command::FAILURE;
        }

        if (($handle = fopen($csvFile, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ',');
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {

                if (count($data) < 4) {
                    $io->warning('Incomplete data row: ' . json_encode($data));
                    continue;
                }

                $movie = new Movie();
                $budget = is_numeric($data[0]) ? $data[0] : '0';
                $movie->setBudget($budget);
                $movie->setIdMovie((int)$data[1]);
                $movie->setOriginalTitle($data[2]);
                $movie->setOverview($data[3]);
                $movie->setReleaseDate(new DateTime($data[4]));

                $this->entityManager->persist($movie);
            }
            fclose($handle);

            $this->entityManager->flush();

            // Index all movies in Algolia
            $movies = $this->entityManager->getRepository(Movie::class)->findAll();
            $moviesArray = [];

            foreach ($movies as $movie) {
                $moviesArray[] = [
                    'id' => $movie->getIdMovie(),
                    'title' => $movie->getOriginalTitle(),
                    'overview' => $movie->getOverview(),
                    'release_date' => $movie->getReleaseDate()->format('d-m-Y'),
                    'budget' => $movie->getBudget(),
                ];
            }

            $this->searchService->index($moviesArray, 'movie');
        }

        $io->success('Movies have been indexed successfully.');

        return Command::SUCCESS;
    }
}