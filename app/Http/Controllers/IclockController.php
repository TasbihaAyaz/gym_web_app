<?php

namespace App\Http\Controllers;

use App\Services\ZktecoAdmsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * ZKTeco ADMS / Push protocol endpoints for devices like K50.
 * Device must reach this app on the LAN (no login — CSRF exempt).
 */
class IclockController extends Controller
{
    public function __construct(private ZktecoAdmsService $adms)
    {
    }

    public function cdata(Request $request): Response
    {
        $serial = (string) $request->query('SN', $request->input('SN', ''));
        if ($serial === '') {
            return $this->plain('OK');
        }

        $device = $this->adms->touchDevice($serial, $request->ip());

        // Handshake / options poll
        if ($request->isMethod('get') || $request->query('options') === 'all' || $request->has('options')) {
            return $this->plain($this->adms->handshakeOptions($device));
        }

        $table = strtoupper((string) $request->query('table', $request->input('table', '')));
        $body = (string) $request->getContent();
        $stamp = $request->query('Stamp', $request->input('Stamp'));
        $stampInt = is_numeric($stamp) ? (int) $stamp : null;

        try {
            if ($table === 'ATTLOG') {
                $count = $this->adms->ingestAttLog($serial, $body, $stampInt);

                return $this->plain('OK: ' . $count);
            }

            if ($table === 'OPERLOG' || $table === 'OPLOG') {
                $count = $this->adms->ingestOperLog($serial, $body, $stampInt);

                return $this->plain('OK: ' . $count);
            }

            // Unknown table — acknowledge so device does not retry forever
            $lines = preg_split('/\r\n|\r|\n/', trim($body)) ?: [];
            $count = count(array_filter($lines, fn ($l) => trim($l) !== ''));

            return $this->plain('OK: ' . $count);
        } catch (\Throwable $e) {
            Log::error('ZKTeco ADMS error', [
                'sn' => $serial,
                'table' => $table,
                'message' => $e->getMessage(),
            ]);

            return $this->plain('ERROR: 0');
        }
    }

    public function getrequest(Request $request): Response
    {
        $serial = (string) $request->query('SN', '');
        if ($serial !== '') {
            $this->adms->touchDevice($serial, $request->ip());
        }

        // No queued commands yet
        return $this->plain('OK');
    }

    public function deviceCmd(Request $request): Response
    {
        $serial = (string) $request->query('SN', $request->input('SN', ''));
        if ($serial !== '') {
            $this->adms->touchDevice($serial, $request->ip());
        }

        return $this->plain('OK');
    }

    public function test(): Response
    {
        return $this->plain('OK');
    }

    private function plain(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }
}
