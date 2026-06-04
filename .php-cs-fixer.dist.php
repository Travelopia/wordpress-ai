<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use Travelopia\WordPressCodingStandards\TravelopiaFixersConfig;

$finder = Finder::create()
	->in( [
		__DIR__ . '/inc',
	] )
	->exclude( [
		'dist',
		'vendor',
		'wordpress',
		'Providers/Bedrock',
	] )
	->append( [
		__FILE__,
		__DIR__ . '/plugin.php',
	] )
	->name( '*.php' )
	->ignoreVCS( true );

$config = new Config();
$config = TravelopiaFixersConfig::create()
	->setRiskyAllowed( true )
	->setIndent( "\t" )
	->setLineEnding( "\n" )
	->setParallelConfig( ParallelConfigFactory::detect() )
	->setRules( TravelopiaFixersConfig::getRules() )
	->setFinder( $finder );

return $config;
