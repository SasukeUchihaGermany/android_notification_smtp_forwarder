<?php
// date_default_timezone_set("Asia/Tehran");
// $date=new DateTime(null, new DateTimeZone('Asia/Tehran'));
// $date=$date->format("Y/m/d-H:i:s");
include "jdf.php";
include "config.php";

function smtp_read_response($fp) {
	$response = '';
	while (!feof($fp)) {
		$line = fgets($fp, 515);
		if ($line === false) {
			break;
		}
		$response .= $line;
		if (strlen($line) >= 4 && $line[3] === ' ') {
			break;
		}
	}
	return $response;
}

function smtp_expect_ok($response, $expectedPrefix) {
	$code = substr(trim($response), 0, 3);
	return $code === $expectedPrefix;
}

function smtp_write($fp, $cmd) {
	fwrite($fp, $cmd . "\r\n");
}

function smtp_encode_header($text) {
	if ($text === '') {
		return '';
	}
	if (preg_match('/^[\x20-\x7E]+$/', $text)) {
		return $text;
	}
	return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function smtp_send_gmail($subject, $body) {
	$useImplicitTls = defined('SMTP_PORT') && ((int)SMTP_PORT === 465);
	$scheme = $useImplicitTls ? 'ssl://' : 'tcp://';
	$remote = $scheme . SMTP_HOST . ':' . SMTP_PORT;
	$context = stream_context_create();
	$fp = stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
	if ($fp === false) {
		throw new Exception('SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
	}
	stream_set_timeout($fp, 15);

	$banner = smtp_read_response($fp);
	if (!smtp_expect_ok($banner, '220')) {
		fclose($fp);
		throw new Exception('SMTP banner error: ' . $banner);
	}

	$host = 'localhost';
	smtp_write($fp, 'EHLO ' . $host);
	$ehlo = smtp_read_response($fp);
	if (!smtp_expect_ok($ehlo, '250')) {
		fclose($fp);
		throw new Exception('EHLO failed: ' . $ehlo);
	}

	if (!$useImplicitTls) {
		smtp_write($fp, 'STARTTLS');
		$starttls = smtp_read_response($fp);
		if (!smtp_expect_ok($starttls, '220')) {
			fclose($fp);
			throw new Exception('STARTTLS failed: ' . $starttls);
		}
		$cryptoOk = stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
		if ($cryptoOk !== true) {
			fclose($fp);
			throw new Exception('TLS negotiation failed');
		}

		smtp_write($fp, 'EHLO ' . $host);
		$ehlo2 = smtp_read_response($fp);
		if (!smtp_expect_ok($ehlo2, '250')) {
			fclose($fp);
			throw new Exception('EHLO after STARTTLS failed: ' . $ehlo2);
		}
	}

	smtp_write($fp, 'AUTH LOGIN');
	$auth = smtp_read_response($fp);
	if (!smtp_expect_ok($auth, '334')) {
		fclose($fp);
		throw new Exception('AUTH LOGIN rejected: ' . $auth);
	}

	smtp_write($fp, base64_encode(SMTP_USER));
	$userResp = smtp_read_response($fp);
	if (!smtp_expect_ok($userResp, '334')) {
		fclose($fp);
		throw new Exception('SMTP username rejected: ' . $userResp);
	}

	smtp_write($fp, base64_encode(SMTP_PASS));
	$passResp = smtp_read_response($fp);
	if (!smtp_expect_ok($passResp, '235')) {
		fclose($fp);
		throw new Exception('SMTP password rejected: ' . $passResp);
	}

	smtp_write($fp, 'MAIL FROM:<' . MAIL_FROM . '>');
	$fromResp = smtp_read_response($fp);
	if (!smtp_expect_ok($fromResp, '250')) {
		fclose($fp);
		throw new Exception('MAIL FROM failed: ' . $fromResp);
	}

	smtp_write($fp, 'RCPT TO:<' . MAIL_TO . '>');
	$toResp = smtp_read_response($fp);
	if (!smtp_expect_ok($toResp, '250') && !smtp_expect_ok($toResp, '251')) {
		fclose($fp);
		throw new Exception('RCPT TO failed: ' . $toResp);
	}

	smtp_write($fp, 'DATA');
	$dataResp = smtp_read_response($fp);
	if (!smtp_expect_ok($dataResp, '354')) {
		fclose($fp);
		throw new Exception('DATA failed: ' . $dataResp);
	}

	$headers = '';
	$headers .= 'From: ' . smtp_encode_header(MAIL_FROM_NAME) . ' <' . MAIL_FROM . ">\r\n";
	$headers .= 'To: <' . MAIL_TO . ">\r\n";
	$headers .= 'Subject: ' . smtp_encode_header($subject) . "\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
	$headers .= "Content-Transfer-Encoding: 8bit\r\n";

	$message = $headers . "\r\n" . str_replace("\n", "\r\n", $body);
	$message = str_replace("\r\n.", "\r\n..", $message);

	smtp_write($fp, $message . "\r\n.");
	$sendResp = smtp_read_response($fp);
	if (!smtp_expect_ok($sendResp, '250')) {
		fclose($fp);
		throw new Exception('Message not accepted: ' . $sendResp);
	}

	smtp_write($fp, 'QUIT');
	smtp_read_response($fp);
	fclose($fp);
}

function log_mail_error($msg) {
	$line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
	file_put_contents(__DIR__ . '/mail_error.log', $line, FILE_APPEND);
}

$date=jdate("Y/m/d-H:i:s");

$title = null;
$text = null;
$pack = null;
$ticker = null;
$token = null;

if(isset($_POST["title"], $_POST["text"])) {
	$title = $_POST["title"];
	$text = $_POST["text"];
	if (isset($_POST["package"])) {
		$pack = $_POST["package"];
	}
	if (isset($_POST["ticker"])) {
		$ticker = $_POST["ticker"];
	}
	if (isset($_POST["token"])) {
		$token = $_POST["token"];
	}
}
else if(isset($_GET["title"], $_GET["text"])) {
	$title = $_GET["title"];
	$text = $_GET["text"];
}

if ($title === null || $text === null) {
	http_response_code(400);
	print "bad request";
	exit;
}

if (defined('NOTIF_TOKEN') && NOTIF_TOKEN !== '') {
	if ($token === null || $token !== NOTIF_TOKEN) {
		http_response_code(403);
		print "forbidden";
		exit;
	}
}

$data="- " . $date . " : " . $title . " = " .$text . "\n";
file_put_contents("data.txt", $data, FILE_APPEND);

$subject = "Phone Notification";
if (trim($title) !== '') {
	$subject = "Phone Notification: " . $title;
}

$body = $date . "\n";
$body .= "Title: " . $title . "\n";
$body .= "Text: " . $text . "\n";
if ($pack !== null && $pack !== '') {
	$body .= "Package: " . $pack . "\n";
}
if ($ticker !== null && $ticker !== '') {
	$body .= "Ticker: " . $ticker . "\n";
}

try {
	smtp_send_gmail($subject, $body);
} catch (Exception $e) {
	log_mail_error($e->getMessage());
}

print "ok";
