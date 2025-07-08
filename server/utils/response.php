<?php

function send_response($data, $statusCode = 200)
{
  http_response_code($statusCode);
  echo json_encode($data);
  exit;
}

function send_error($message, $statusCode = 400, $details = [])
{
  $response = ['error' => $message];
  if (!empty($details)) {
    $response['details'] = $details;
  }
  send_response($response, $statusCode);
}

function send_success($message, $statusCode = 200, $data = [])
{
  $response = ['message' => $message];
  if (!empty($data)) {
    $response['data'] = $data;
  }
  send_response($response, $statusCode);
}

function send_validation_errors($errors, $statusCode = 422)
{
  send_error('Validation failed', $statusCode, $errors);
}

function send_not_found($resource = 'Resource')
{
  send_error("{$resource} not found", 404);
}

function send_unauthorized($message = 'Unauthorized', $details = [])
{
  send_error($message, 401, $details);
}

function send_forbidden($message = 'Forbidden')
{
  send_error($message, 403);
}

function send_method_not_allowed()
{
  send_error('Method not allowed', 405);
}

function send_internal_server_error($message = 'Internal Server Error')
{
  send_error($message, 500);
}

function send_created($data = [], $message = 'Resource created successfully')
{
  send_response(['message' => $message, 'data' => $data], 201);
}

function send_no_content()
{
  http_response_code(204);
  exit;
}

function send_bad_request($message = 'Bad request')
{
  send_error($message, 400);
}

function send_conflict($message = 'Conflict')
{
  send_error($message, 409);
}

function send_service_unavailable($message = 'Service Unavailable')
{
  send_error($message, 503);
}

function send_too_many_requests($message = 'Too Many Requests')
{
  send_error($message, 429);
}

function send_unprocessable_entity($errors = [])
{
  send_validation_errors($errors);
}

function send_not_implemented($message = 'Not Implemented')
{
  send_error($message, 501);
}

function send_gateway_timeout($message = 'Gateway Timeout')
{
  send_error($message, 504);
}

function send_network_authentication_required($message = 'Network Authentication Required')
{
  send_error($message, 511);
}

function send_precondition_failed($message = 'Precondition Failed')
{
  send_error($message, 412);
}

function send_precondition_required($message = 'Precondition Required')
{
  send_error($message, 428);
}

function send_request_header_fields_too_large($message = 'Request Header Fields Too Large')
{
  send_error($message, 431);
}

function send_unavailable_for_legal_reasons($message = 'Unavailable For Legal Reasons')
{
  send_error($message, 451);
}

function send_client_closed_request($message = 'Client Closed Request')
{
  send_error($message, 499);
}

function send_invalid_token($message = 'Invalid Token')
{
  send_error($message, 498);
}

function send_token_required($message = 'Token Required')
{
  send_error($message, 499);
}

function send_login_timeout($message = 'Login Timeout')
{
  send_error($message, 440);
}

function send_retry_with($message = 'Retry With')
{
  send_error($message, 449);
}

function send_blocked_by_windows_parental_controls($message = 'Blocked by Windows Parental Controls')
{
  send_error($message, 450);
}

function send_redirect($url, $statusCode = 302)
{
  header("Location: {$url}", true, $statusCode);
  exit;
}

function send_permanent_redirect($url)
{
  send_redirect($url, 301);
}

function send_temporary_redirect($url)
{
  send_redirect($url, 307);
}

function send_permanent_redirect_with_method_change($url)
{
  send_redirect($url, 308);
}

function send_see_other($url)
{
  send_redirect($url, 303);
}

function send_not_modified()
{
  http_response_code(304);
  exit;
}

function send_use_proxy($url)
{
  header("Location: {$url}", true, 305);
  exit;
}

function send_switch_proxy()
{
  http_response_code(306);
  exit;
}

function send_multiple_choices($choices)
{
  send_response($choices, 300);
}

function send_moved_permanently($url)
{
  send_permanent_redirect($url);
}

function send_found($url)
{
  send_redirect($url);
}

function send_processing()
{
  http_response_code(102);
  exit;
}

function send_early_hints($hints)
{
  http_response_code(103);
  foreach ($hints as $hint) {
    header($hint, false);
  }
  exit;
}

function send_no_response()
{
  http_response_code(205);
  exit;
}

function send_reset_content()
{
  http_response_code(205);
  exit;
}

function send_partial_content($data, $range)
{
  http_response_code(206);
  header("Content-Range: {$range}");
  echo json_encode($data);
  exit;
}

function send_multi_status($data)
{
  http_response_code(207);
  echo json_encode($data);
  exit;
}

function send_already_reported()
{
  http_response_code(208);
  exit;
}

function send_im_used()
{
  http_response_code(226);
  exit;
}
?>