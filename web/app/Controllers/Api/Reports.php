<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 해양쓰레기 Word 보고서 생성 (control/test3.py 래퍼)
 */
class Reports extends BaseController
{
    private function controlRoot(): string
    {
        return realpath(ROOTPATH . '../control') ?: (ROOTPATH . '../control');
    }

    private function pythonBin(): string
    {
        $configured = trim((string) (env('report.python') ?: getenv('REPORT_PYTHON') ?: ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $root = $this->controlRoot();
        $home = getenv('USERPROFILE') ?: (getenv('HOME') ?: '');
        $candidates = array_filter([
            // LLM 보고서용 conda env 우선 (docx + llama-cpp)
            'D:\\conda_envs\\ex\\python.exe',
            $home !== '' ? $home . DIRECTORY_SEPARATOR . 'anaconda3' . DIRECTORY_SEPARATOR . 'envs' . DIRECTORY_SEPARATOR . 'ex' . DIRECTORY_SEPARATOR . 'python.exe' : null,
            $home !== '' ? $home . DIRECTORY_SEPARATOR . 'miniconda3' . DIRECTORY_SEPARATOR . 'envs' . DIRECTORY_SEPARATOR . 'ex' . DIRECTORY_SEPARATOR . 'python.exe' : null,
            'C:\\Users\\K\\anaconda3\\envs\\ex\\python.exe',
            $root . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
            $root . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
        ]);

        foreach ($candidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }
            // python-docx 가 있는 인터프리터를 우선 사용 (test3.py 필수)
            $check = @proc_open(
                [$candidate, '-c', 'import docx'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                null,
                ['bypass_shell' => true]
            );
            if (is_resource($check)) {
                stream_get_contents($pipes[1]);
                stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($check);
                if ($code === 0) {
                    return $candidate;
                }
            }
        }

        return 'python';
    }

    private function reportDir(): string
    {
        return $this->controlRoot() . DIRECTORY_SEPARATOR . 'report_test_results';
    }

    private function surveyDir(): string
    {
        return $this->controlRoot() . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'surveys';
    }

    /** POST /api/reports/generate — test3.py와 동일하게 최신 조사 JSON으로 보고서 생성 */
    public function generate(): ResponseInterface
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        $control = $this->controlRoot();
        $script  = $control . DIRECTORY_SEPARATOR . 'test3.py';
        if (!is_file($script)) {
            return $this->response->setStatusCode(500)->setJSON([
                'ok'      => false,
                'message' => 'test3.py 를 찾을 수 없습니다: ' . $script,
            ]);
        }

        $surveyDir = $this->surveyDir();
        $latest    = $surveyDir . DIRECTORY_SEPARATOR . 'latest.json';
        if (!is_file($latest) && is_dir($surveyDir)) {
            $candidates = glob($surveyDir . DIRECTORY_SEPARATOR . 'MISSION-*.json') ?: [];
            usort($candidates, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
            if ($candidates === []) {
                return $this->response->setStatusCode(404)->setJSON([
                    'ok'      => false,
                    'message' => '조사 JSON이 없습니다. 드론 스트림을 켠 뒤 끄면 logs/surveys/ 에 파일이 생성됩니다.',
                ]);
            }
        } elseif (!is_file($latest) && !is_dir($surveyDir)) {
            return $this->response->setStatusCode(404)->setJSON([
                'ok'      => false,
                'message' => '조사 JSON 폴더가 없습니다: ' . $surveyDir,
            ]);
        }

        $body       = $this->request->getJSON(true) ?? [];
        $sampleOnly = !empty($body['sample_only']);
        $inputJson  = isset($body['input_json']) ? trim((string) $body['input_json']) : '';

        $python = $this->pythonBin();
        $cmd    = [
            $python,
            $script,
            '--latest',
        ];

        if ($inputJson !== '') {
            // 절대 경로 또는 surveys 상대 파일명만 허용
            $resolved = $inputJson;
            if (!preg_match('#^[a-zA-Z]:\\\\|^/#', $inputJson)) {
                $resolved = $surveyDir . DIRECTORY_SEPARATOR . basename($inputJson);
            }
            $real = realpath($resolved);
            $surveyReal = realpath($surveyDir);
            if ($real === false || $surveyReal === false || !str_starts_with($real, $surveyReal)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'ok'      => false,
                    'message' => '허용되지 않은 조사 JSON 경로입니다.',
                ]);
            }
            $cmd = [$python, $script, '--input-json', $real];
        }

        if ($sampleOnly) {
            $cmd[] = '--sample-only';
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pathExtra = [];
        foreach ([
            'C:\\Program Files\\NVIDIA GPU Computing Toolkit\\CUDA\\v12.6\\bin',
            'C:\\Program Files\\NVIDIA GPU Computing Toolkit\\CUDA\\v12.4\\bin',
            'C:\\Program Files\\NVIDIA GPU Computing Toolkit\\CUDA\\v12.2\\bin',
            'C:\\Program Files\\NVIDIA GPU Computing Toolkit\\CUDA\\v12.1\\bin',
            'C:\\Program Files\\NVIDIA GPU Computing Toolkit\\CUDA\\v11.8\\bin',
        ] as $cudaBin) {
            if (is_dir($cudaBin)) {
                $pathExtra[] = $cudaBin;
            }
        }

        $oldPath = getenv('PATH') ?: '';
        if ($pathExtra !== []) {
            putenv('PATH=' . implode(PATH_SEPARATOR, $pathExtra) . PATH_SEPARATOR . $oldPath);
        }

        $process = proc_open(
            $cmd,
            $descriptors,
            $pipes,
            $control,
            null,
            ['bypass_shell' => true]
        );

        if ($pathExtra !== []) {
            putenv('PATH=' . $oldPath);
        }

        if (!is_resource($process)) {
            return $this->response->setStatusCode(500)->setJSON([
                'ok'      => false,
                'message' => '보고서 생성 프로세스를 시작할 수 없습니다.',
            ]);
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], true);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $resultJson = null;
        if (preg_match('/RESULT_JSON:(.+)$/m', $stdout, $m)) {
            $resultJson = json_decode(trim($m[1]), true);
        }

        $docxPath = null;
        if (is_array($resultJson) && !empty($resultJson['docx'])) {
            $docxPath = (string) $resultJson['docx'];
        } elseif (preg_match('/DOCX\s*:\s*(.+)/u', $stdout, $m)) {
            $docxPath = trim($m[1]);
        }

        if ($exitCode !== 0 || !$docxPath || !is_file($docxPath)) {
            $detail = trim($stderr !== '' ? $stderr : $stdout);
            if ($detail === '') {
                $detail = 'exit code ' . $exitCode;
            }
            return $this->response->setStatusCode(500)->setJSON([
                'ok'        => false,
                'message'   => '보고서 생성에 실패했습니다.',
                'detail'    => mb_substr($detail, 0, 4000),
                'exit_code' => $exitCode,
            ]);
        }

        $basename = basename($docxPath);
        $missionId = is_array($resultJson) ? ($resultJson['mission_id'] ?? null) : null;

        return $this->response->setJSON([
            'ok'          => true,
            'message'     => '기관용 Word 보고서가 생성되었습니다.',
            'mission_id'  => $missionId,
            'filename'    => $basename,
            'download_url'=> '/api/reports/download/' . rawurlencode($basename),
            'docx'        => $docxPath,
            'runtime'     => is_array($resultJson) ? ($resultJson['runtime'] ?? null) : null,
        ]);
    }

    /** GET /api/reports/download/(:segment) */
    public function download(string $filename): ResponseInterface
    {
        $filename = basename(rawurldecode($filename));
        if (!preg_match('/^[A-Za-z0-9._\-]+\.docx$/i', $filename)) {
            return $this->response->setStatusCode(400)->setJSON([
                'ok'      => false,
                'message' => '잘못된 파일명입니다.',
            ]);
        }

        $path = $this->reportDir() . DIRECTORY_SEPARATOR . $filename;
        $real = realpath($path);
        $dir  = realpath($this->reportDir());

        if ($real === false || $dir === false || !str_starts_with($real, $dir) || !is_file($real)) {
            return $this->response->setStatusCode(404)->setJSON([
                'ok'      => false,
                'message' => '보고서 파일을 찾을 수 없습니다.',
            ]);
        }

        return $this->response->download($real, null)->setFileName($filename);
    }
}
