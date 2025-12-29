<?php
class Hungarian {
    private $costMatrix;
    private $numRows;
    private $numCols;
    private $labelsRow;
    private $labelsCol;
    private $minSlackValue;
    private $minSlackRow;
    private $matchRow;
    private $matchCol;
    private $committedRow;
    private $parent;

    public function __construct($costMatrix) {
        $this->costMatrix = $costMatrix;
        $this->numRows = count($costMatrix);
        $this->numCols = count($costMatrix[0]);
        $this->labelsRow = array_fill(0, $this->numRows, 0);
        $this->labelsCol = array_fill(0, $this->numCols, 0);
        $this->minSlackValue = array_fill(0, $this->numCols, 0);
        $this->minSlackRow = array_fill(0, $this->numCols, 0);
        $this->matchRow = array_fill(0, $this->numRows, -1);
        $this->matchCol = array_fill(0, $this->numCols, -1);
        $this->committedRow = array_fill(0, $this->numRows, false);
        $this->parent = array_fill(0, $this->numCols, -1);
    }

    public function solve() {
        $this->initializeLabels();
        $this->greedyMatch();

        for ($i = 0; $i < $this->numRows; $i++) {
            if ($this->matchRow[$i] == -1) {
                $this->augment($i);
            }
        }
    }

    private function initializeLabels() {
        for ($i = 0; $i < $this->numRows; $i++) {
            $this->labelsRow[$i] = max($this->costMatrix[$i]);
        }
    }

    private function greedyMatch() {
        for ($i = 0; $i < $this->numRows; $i++) {
            for ($j = 0; $j < $this->numCols; $j++) {
                if ($this->matchCol[$j] == -1 && $this->costMatrix[$i][$j] == $this->labelsRow[$i] + $this->labelsCol[$j]) {
                    $this->matchRow[$i] = $j;
                    $this->matchCol[$j] = $i;
                    break;
                }
            }
        }
    }

    private function augment($k) {
        if ($this->matchRow[$k] != -1) return;

        $this->committedRow[$k] = true;
        $queue = [$k];
        $parent = array_fill(0, $this->numCols, -1);

        while (!empty($queue)) {
            $i = array_shift($queue);
            for ($j = 0; $j < $this->numCols; $j++) {
                if ($this->costMatrix[$i][$j] == $this->labelsRow[$i] + $this->labelsCol[$j]) {
                    if ($this->matchCol[$j] == -1) {
                        $this->parent[$j] = $i;
                        return true;
                    } elseif (!$this->committedRow[$this->matchCol[$j]]) {
                        array_push($queue, $this->matchCol[$j]);
                        $this->committedRow[$this->matchCol[$j]] = true;
                        $this->parent[$j] = $i;
                    }
                }
            }
        }

        return false;
    }

    private function updateLabels() {
        $delta = PHP_INT_MAX;

        for ($j = 0; $j < $this->numCols; $j++) {
            if ($this->minSlackValue[$j] < $delta && $this->parent[$j] == -1) {
                $delta = $this->minSlackValue[$j];
            }
        }

        for ($i = 0; $i < $this->numRows; $i++) {
            if ($this->committedRow[$i]) {
                $this->labelsRow[$i] -= $delta;
            }
        }

        for ($j = 0; $j < $this->numCols; $j++) {
            if ($this->parent[$j] != -1) {
                $this->labelsCol[$j] += $delta;
            } else {
                $this->minSlackValue[$j] -= $delta;
            }
        }
    }

    public function getResult() {
        $result = [];
        for ($i = 0; $i < $this->numRows; $i++) {
            if ($this->matchRow[$i] != -1) {
                $result[$this->matchRow[$i]] = $i; // 保存用户索引与时间段索引的映射关系
            }
        }
        return $result;
    }
}
?>
