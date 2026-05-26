<?php

declare(strict_types=1);

namespace Pvmlibs\FlexId\Tools;

use Pvmlibs\FlexId\Encoders\EncoderContract;
use Pvmlibs\FlexId\Encrypters\EncrypterContract;
use Pvmlibs\FlexId\Exceptions\IdConfigurationException;
use Pvmlibs\FlexId\Exceptions\IdGeneratorException;
use Pvmlibs\FlexId\FlexIdGenerator;
use Pvmlibs\FlexId\Resolvers\ApcuTimestepWorkerResolver;
use Pvmlibs\FlexId\Resolvers\StaticWorkerResolver;
use Pvmlibs\FlexId\Signers\SignerContract;

/**
 * Shows id time range, throughput and lengths for different timestamp bit shifts and years.
 */
class IdStats
{
    /**
     * @param list<int> $timestampBitShifts
     * @param list<int> $yearsDelta
     */
    public function __construct(
        private FlexIdGenerator $generator,
        private ?EncoderContract $encoder = null,
        private ?EncrypterContract $encrypter = null,
        private ?SignerContract $signer = null,
        private array $timestampBitShifts = [0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20],
        private array $yearsDelta = [0, 10, 50, 100, 250],
        private int $minIdTestRange = 1000,
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function generateDistributionData(): array
    {
        $data = [];
        $now = (int) \date('Y');

        foreach ($this->timestampBitShifts as $timestampShift) {
            $resolverConfig = $this->generator->workerResolver->getConfiguration();
            try {
                if ($this->generator->workerResolver instanceof StaticWorkerResolver) {
                    $resolver = new StaticWorkerResolver(
                        workerHandlerFn: fn () => 0,
                        workersBits: $resolverConfig->workersBits,
                        sequenceBits: $resolverConfig->sequenceBits,
                        groupsBits: $resolverConfig->groupsBits,
                        workerLockFilePath: null,
                        timestampBitshift: $timestampShift,
                        timestampOffset: $resolverConfig->timestampOffset,
                    );
                } elseif ($this->generator->workerResolver instanceof ApcuTimestepWorkerResolver) {
                    $resolver = new ApcuTimestepWorkerResolver(
                        workersBits: $resolverConfig->workersBits,
                        sequenceBits: $resolverConfig->sequenceBits,
                        groupsBits: $resolverConfig->groupsBits,
                        useNewWorkerOnSequenceOverflow: $resolverConfig->useNewWorkerOnSequenceOverflow,
                        workerLockFilePath: null,
                        timestampBitshift: $timestampShift,
                        timestampOffset: $resolverConfig->timestampOffset,
                    );
                } else {
                    $resolver = new ($this->generator->workerResolver::class)(
                        workersBits: $resolverConfig->workersBits,
                        sequenceBits: $resolverConfig->sequenceBits,
                        groupsBits: $resolverConfig->groupsBits,
                        timestampBitshift: $timestampShift,
                        timestampOffset: $resolverConfig->timestampOffset,
                    );
                }
            } catch (IdConfigurationException) {
                $err = '*';
                $data[$timestampShift] = [
                    'years left' => $err,
                    'ending date' => $err,
                    'ids/second' => $err,
                    'resolution[s]' => $err,
                    'years' => [],
                ];
                foreach ($this->yearsDelta as $yearDelta) {
                    $currentYear = $now + $yearDelta;
                    $data[$timestampShift]['years'][$currentYear] = [
                        'delta year' => $yearDelta,
                        'raw' => $err,
                        'encoded' => $err,
                        'encrypted' => $err,
                    ];
                }
                continue;
            }

            $generator = new FlexIdGenerator(
                workerResolver: $resolver,
            );

            $lifespan = $generator->getTimeRangeYears();
            $throughput = $resolverConfig->maxWorkers * $resolverConfig->maxSequence * (1e9 / $resolver->getConfiguration()->timestepNs);

            $data[$timestampShift] = [
                'years left' => \round($lifespan, 3),
                'ending date' => (new \DateTime('@' . (\time() + $lifespan * 365.25 * 24 * 60 * 60)))->format('Y-m-d'),
                'ids/second' => (int) $throughput,
                'resolution[s]' => \number_format($resolver->getConfiguration()->timestepNs / 1e9, 6),
                'years' => [],
            ];

            foreach ($this->yearsDelta as $yearDelta) {
                $currentYear = $now + $yearDelta;
                $yearMicroseconds = $yearDelta * 12 * 30 * 24 * 60 * 60 * 1_000_000;
                try {
                    $id = $generator->idInTime(\intval(\microtime(true) * 1_000_000) + $yearMicroseconds);
                    $encoded = $this->encoder?->encode($id);
                    $encrypted = $this->encrypter?->encrypt($id);
                } catch (IdGeneratorException) {
                    $id = 'time range exhausted';
                    $encoded = null;
                    $encrypted = null;
                }

                $encodedIdLength = $encoded !== null ? \strlen($encoded) : 'not provided';
                $encryptedIdLength = $encrypted !== null ? \strlen($encrypted) : 'not provided';

                $data[$timestampShift]['years'][$currentYear] = [
                    'delta year' => $yearDelta,
                    'raw' => $id,
                    'encoded' => $encoded . " ({$encodedIdLength})",
                    'encrypted' => $encrypted . " ({$encryptedIdLength})",
                ];
            }
        }

        return $data;
    }

    /**
     * @return array<string, int>
     */
    public function generateIdLengthData(): array
    {
        $results = [];
        $testId = $this->generator->id();
        $results['generator current id length'] = \strlen((string) $testId);
        $results['generator max id length'] = \strlen((string) PHP_INT_MAX);

        if ($this->encoder !== null) {
            $results['encoder current id length'] = \strlen($this->encoder->encode($testId));
            $results['encoder max id length'] = $this->encoder->getMaxEncodedLength();
        }

        if ($this->encrypter !== null) {
            $results['encrypter current id length'] = \strlen($this->encrypter->encrypt($testId));
            $results['encrypter max id length'] = $this->encrypter->getSerializer()->getMaxEncodedLength();

        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function generatePerformanceData(): array
    {
        $total = $this->minIdTestRange;
        $testId = $this->generator->id();

        // adjust testing range for id generation to make more sense in continuous context
        $resolverConfig = $this->generator->workerResolver->getConfiguration();
        $maxThroughput = \intval($resolverConfig->maxWorkers * $resolverConfig->maxSequence * (1e9 / $resolverConfig->timestepNs));
        $idGenTotal = \max(\intdiv($maxThroughput, 10_000), $total);

        $results = [
            'perf' => [],
            'id test range' => $idGenTotal,
        ];

        // test sustain generation
        $start = \hrtime(true);
        for ($i = 0; $i < $idGenTotal; $i++) {
            $this->generator->id();
        }
        $end = \hrtime(true);

        $results['perf']['id generator'] = \round(min($maxThroughput, $idGenTotal / (($end - $start) / 1e9)));

        $start = \hrtime(true);
        $this->generator->bulkIds($idGenTotal);
        $end = \hrtime(true);

        $results['perf']['id generator burst'] = \round(min($maxThroughput, $idGenTotal / (($end - $start) / 1e9)));

        // for encoder/decoder 1000 is enough
        $total = 1000;

        if ($this->encoder !== null) {
            $encoded = $this->encoder->encode($testId);
            $start = \hrtime(true);
            for ($i = 0; $i < $total; $i++) {
                $this->encoder->encode($testId);
            }
            $end = \hrtime(true);
            $results['perf']['id encode'] = \round($total / ($end - $start) * 1e9);

            $start = \hrtime(true);
            for ($i = 0; $i < $total; $i++) {
                $this->encoder->decode($encoded);
            }
            $end = \hrtime(true);
            $results['perf']['id decode'] = \round($total / ($end - $start) * 1e9);
        }

        if ($this->encrypter !== null) {
            $encrypted = $this->encrypter->encrypt($testId);

            $start = \hrtime(true);
            for ($i = 0; $i < $total; $i++) {
                $this->encrypter->encrypt($testId);
            }
            $end = \hrtime(true);
            $results['perf']['id encrypt'] = \round($total / ($end - $start) * 1e9);

            $start = \hrtime(true);
            for ($i = 0; $i < $total; $i++) {
                $this->encrypter->decrypt($encrypted);
            }
            $end = \hrtime(true);
            $results['perf']['id decrypt'] = \round($total / ($end - $start) * 1e9);
        }

        if ($this->signer !== null) {
            $signed = $this->signer->getSignedId('8tYknkpkj58L');

            $start = \hrtime(true);
            for ($i = 0; $i < $total; $i++) {
                $this->signer->getSignedId('8tYknkpkj58L');
            }
            $end = \hrtime(true);
            $results['perf']['id sign'] = \round($total / ($end - $start) * 1e9);

            $start = \hrtime(true);
            for ($i = 0; $i < $total; $i++) {
                $this->signer->getIdFromSigned($signed);
            }
            $end = \hrtime(true);
            $results['perf']['id verify sign'] = \round($total / ($end - $start) * 1e9);
        }

        return $results;
    }

    private function alignToRight(string $text, int $maxLength): string
    {
        $result = '';
        for ($i = 0; $i < $maxLength - \strlen($text); $i++) {
            $result .= ' ';
        }

        return $result . $text;
    }

    public function presentation(bool $info = true, bool $distribution = true, bool $performance = true): string
    {
        $data = $this->generateDistributionData();
        $text = "Stats using\n";
        $config = $this->generator->workerResolver->getConfiguration();
        $class = explode('\\', $this->generator->workerResolver::class);
        $text .= sprintf(
            "Resolver: %s(max workers %d, max sequence %d, max groups %d, timestamp bitshift %d)\n",
            end($class),
            $config->maxWorkers,
            $config->maxSequence,
            $config->maxGroups,
            $config->timestampBitshift,
        );

        if ($this->encoder !== null) {
            $class = explode('\\', $this->encoder::class);
            $text .= sprintf(
                "Encoder: %s(alphabet %s)\n",
                end($class),
                $this->encoder->getAlphabet(),
            );
        }

        if ($this->encrypter !== null) {
            $encrypterClass = explode('\\', $this->encrypter::class);
            $serializerClass = explode('\\', $this->encrypter->getSerializer()::class);
            $text .= sprintf(
                "Encoder: %s(serializer: %s, alphabet %s)\n",
                end($encrypterClass),
                end($serializerClass),
                $this->encrypter->getSerializer()->getAlphabet(),
            );
        }

        if ($info) {
            $text = "Generator info\n\n";

            $info = $this->generator->info();
            foreach ($info as $key => $value) {
                $text .= \str_pad($key, 25) . "| {$value}\n";
            }
        }

        if ($distribution) {
            $text .= "\nId distribution\n";

            $text .= "\ntimestamp\nbit\nshift\t|{$this->formatCell('Years left', 11)}" .
                "|{$this->formatCell('Ending date', 13)}" .
                "|{$this->formatCell('Max ids/s', 13)}" .
                "|{$this->formatCell('Resolution[s]', 15)}" .
                "|{$this->formatCell('Id type', 10)}";

            $headerDone = false;

            foreach ($data as $timestampShift => $idData) {
                if ($headerDone === false) {
                    foreach ($idData['years'] as $year => $yearData) {
                        $text .= "|{$this->formatCell("{$year} (+{$yearData['delta year']})")}";
                    }
                    $text .= "\n" . \implode('', \array_fill(0, 180, '-')) . "\n";
                    $headerDone = true;
                }

                $prefixLine = "{$timestampShift}\t" .
                    "|{$this->formatCell($idData['years left'], 11)}" .
                    "|{$this->formatCell($idData['ending date'], 13)}" .
                    "|{$this->formatCell($idData['ids/second'], 13)}" .
                    "|{$this->formatCell($idData['resolution[s]'], 15)}";

                $keys = ['raw', 'encoded', 'encrypted'];
                foreach ($keys as $key) {
                    $text .= "{$prefixLine}|{$this->formatCell($key, 10)}";
                    foreach ($idData['years'] as $yearData) {
                        $text .= "|{$this->formatCell($yearData[$key])}";
                    }
                    $text .= "\n";

                    $prefixLine = "\t|{$this->formatCell('', 11)}" .
                        "|{$this->formatCell('', 13)}" .
                        "|{$this->formatCell('', 13)}" .
                        "|{$this->formatCell('', 15)}";
                }
                $text .= \implode('', \array_fill(0, 180, '-')) . "\n";

            }

            $text .= "\nLegend:\n";
            $text .= "* - too many bits. Worker bits + sequence bits + group bits + timestamp bit shift must be <= 30\n";
            $text .= "(num) - id length\n";

            $text .= "\nId lengths:\n";
            $lengthData = $this->generateIdLengthData();

            foreach ($lengthData as $key => $value) {
                $text .= \str_pad($key, 28) . '| ' . $value . "\n";
            }
        }

        if ($performance) {
            $perfData = $this->generatePerformanceData();

            $text .= "\nGenerator/encoder/encrypter throughput (generating {$perfData['id test range']} id):\n";
            $maxLength = \strlen((string) $perfData['perf']['id generator burst']);

            foreach ($perfData['perf'] as $key => $value) {
                $text .= \str_pad($key, 20) . '| ' . $this->alignToRight((string) $value, $maxLength) . " [ids/sec]\n";
            }
            $text .= "\nId generator reflects approx. continuous id generation with 1 worker. Performance can be affected by bits configuration.\n";
        }

        return $text . "\n";
    }

    private function formatCell(string|int|float $text, int $size = 20): string
    {
        return \str_pad((string) $text, $size);
    }
}
