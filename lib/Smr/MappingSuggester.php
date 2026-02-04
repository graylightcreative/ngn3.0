<?php
namespace NGN\Lib\Smr;

class MappingSuggester
{
    /**
     * Suggest canonical fields based on provided header strings.
     * Returns array like:
     * {
     *   artist: { header: 'Artist', confidence: 100 }?,
     *   track: { header: 'Track Title', confidence: 92 }?,
     *   spins: { header: 'Spins', confidence: 100 }?,
     *   date: { header: 'Date', confidence: 90 }?,
     * }
     */
    public function suggest(array $headers): array
    {
        $normHeaders = [];
        foreach ($headers as $h) {
            $h = (string)$h;
            $normHeaders[$h] = $this->normalize($h);
        }
        $out = [];
        $out['artist'] = $this->bestMatch($normHeaders, ['artist','artist_name','performer','band']);
        $out['track'] = $this->bestMatch($normHeaders, ['track','title','song','track_title']);
        $out['spins'] = $this->bestMatch($normHeaders, ['spins','plays','count','spin_count','play_count']);
        $out['date']  = $this->bestMatch($normHeaders, ['date','played_at','time','timestamp','datetime']);
        // Filter nulls
        return array_filter($out, fn($v) => $v !== null);
    }

    private function normalize(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return $s;
    }

    private function bestMatch(array $normHeaders, array $candidates): ?array
    {
        $best = null;
        $bestScore = -1;
        foreach ($normHeaders as $orig => $norm) {
            foreach ($candidates as $cand) {
                $score = $this->score($norm, $cand);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = ['header' => $orig, 'confidence' => $score];
                }
                if ($bestScore === 100) break 2; // perfect match
            }
        }
        return $bestScore > 0 ? $best : null;
    }

    private function score(string $norm, string $target): int
    {
        if ($norm === $target) return 100;
        if (str_contains($norm, $target)) return 90;
        if (str_contains($target, $norm)) return 70;
        // crude similarity metric by common tokens
        $a = explode(' ', $norm);
        $b = explode(' ', $target);
        $intersect = count(array_intersect($a, $b));
        $union = max(count(array_unique(array_merge($a, $b))), 1);
        return (int)round(($intersect / $union) * 60);
    }
}
