<?php
/**
 * BenchCommand is a console command to check benchmarks
 *
 * To use this command, enter the following on the command line:
 * <pre>
 * yiimp bench help
 * </pre>
 */
class BenchCommand extends CConsoleCommand
{
	protected $basePath;

	/**
	 * Execute the action.
	 * @param array $args command line parameters specific for this command
	 * @return integer non zero application exit code after printing help
	 */
	public function run($args)
	{
		$runner=$this->getCommandRunner();
		$commands=$runner->commands;

		$root = realpath(Yii::app()->getBasePath().DIRECTORY_SEPARATOR.'..');
		$this->basePath = str_replace(DIRECTORY_SEPARATOR, '/', $root);

		if (!isset($args[0]) || $args[0] == 'help') {

			echo "Yiimp shift command\n";
			echo "Usage: yiimp bench check <algo> [chip] - report average and min/max\n";
			echo "       yiimp bench rates <chip> - search for missing gpu data\n";
			return 1;

		} else if ($args[0] == 'check') {
			return $this->checkBenchAlgo($args);

		} else if ($args[0] == 'rates') {
			return $this->ratesBenchAlgos($args);

		} else {
			$this->run(array('help'));
			return 1;
		}

		return 1;
	}

	/**
	 * Provides the command description.
	 * @return string the command description.
	 */
	public function getHelp()
	{
		return $this->run(array('help'));
	}

	////////////////////////////////////////////////////////////////////////////////////

	public function checkBenchAlgo($args)
	{
		if (count($args) < 2)
			die("usage: bench check <algo> [chip]\n");

		require_once(app()->getModulePath().'/bench/functions.php');

		$algo = arraySafeVal($args, 1);
		$chip = arraySafeVal($args, 2);

		$chips = dbocolumn("SELECT DISTINCT C.chip as name FROM benchmarks B "
			."INNER JOIN bench_chips C ON C.id=B.idchip WHERE algo=:algo AND C.devicetype='gpu' ORDER BY name",
			array(':algo'=>$algo)
		);

		foreach($chips as $c) {
			if (!empty($chip) && $c != $chip) continue;
			$decrnd = 0;
			$rates = dborow("SELECT AVG(khps) AS avg, MIN(khps) AS min, MAX(khps) AS max, COUNT(id) as cnt FROM benchmarks WHERE algo=:algo AND chip=:chip",
				array(':algo'=>$algo, ':chip'=>$c)
			);
			$avg = (double) round($rates['avg'],$decrnd);
			$min = (double) round($rates['min'],$decrnd);
			$max = (double) round($rates['max'],$decrnd);
			$cnt = round($rates['cnt'],$decrnd);
			echo "$algo $c\t$avg kH/s $min-$max ($cnt records)\n";
		}

		return 0;
	}

	////////////////////////////////////////////////////////////////////////////////////

	public function ratesBenchAlgos($args)
	{
		if (count($args) < 1)
			die("usage: bench rates <chip>\n");

		require_once(app()->getModulePath().'/bench/functions.php');

		$chip = arraySafeVal($args, 1);
		$rates = dbolist("SELECT algo, AVG(khps) AS avg, MIN(khps) AS min, MAX(khps) AS max, COUNT(id) as cnt ".
			" FROM benchmarks WHERE chip=:chip GROUP BY algo ORDER BY algo",
			array(':chip'=>$chip)
		);
		foreach($rates as $r) {
			$algo = $r['algo'];
			$decrnd = 0;
			$avg = (double) round($r['avg'],$decrnd);
			$min = (double) round($r['min'],$decrnd);
			$max = (double) round($r['max'],$decrnd);
			$cnt = round($r['cnt'],$decrnd);
			echo "$chip $algo\t$avg kH/s $min-$max ($cnt records)\n";
		}

		return 0;
	}

}
