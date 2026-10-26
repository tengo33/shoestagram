// Paste this entire file into a new Apps Script project owned by the Gmail sender.
// Set SHOESTAGRAM_MAIL_SECRET in Project Settings > Script Properties.

function shoestagramRelayResult_(value) {
  return ContentService.createTextOutput(JSON.stringify(value))
    .setMimeType(ContentService.MimeType.JSON);
}

function shoestagramRelayEqual_(left, right) {
  if (left.length !== right.length) return false;
  var mismatch = 0;
  for (var index = 0; index < left.length; index++) {
    mismatch |= left.charCodeAt(index) ^ right.charCodeAt(index);
  }
  return mismatch === 0;
}

// Run this once in the editor to grant the script permission to send mail.
function authorizeMail() {
  MailApp.getRemainingDailyQuota();
}

function doPost(e) {
  var input;
  try {
    input = JSON.parse(e && e.postData ? e.postData.contents : '{}');
  } catch (error) {
    return shoestagramRelayResult_({ok: false, code: 'invalid_json'});
  }

  var secret = PropertiesService.getScriptProperties().getProperty('SHOESTAGRAM_MAIL_SECRET') || '';
  var supplied = input && typeof input.secret === 'string' ? input.secret : '';
  if (secret.length < 32 || !shoestagramRelayEqual_(supplied, secret)) {
    return shoestagramRelayResult_({ok: false, code: 'unauthorized'});
  }

  var to = input && typeof input.to === 'string' ? input.to.trim() : '';
  var subject = input && typeof input.subject === 'string' ? input.subject.trim() : '';
  var body = input && typeof input.body === 'string' ? input.body : '';
  var replyTo = input && typeof input.replyTo === 'string' ? input.replyTo.trim() : '';
  var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailPattern.test(to) || (replyTo && !emailPattern.test(replyTo)) ||
      !subject || subject.length > 200 || /[\r\n]/.test(subject) ||
      !body || body.length > 100000) {
    return shoestagramRelayResult_({ok: false, code: 'invalid_message'});
  }

  try {
    if (MailApp.getRemainingDailyQuota() < 1) {
      return shoestagramRelayResult_({ok: false, code: 'quota_exceeded'});
    }
    var options = {to: to, subject: subject, body: body, name: 'Shoestagram'};
    if (replyTo) options.replyTo = replyTo;
    MailApp.sendEmail(options);
    return shoestagramRelayResult_({ok: true});
  } catch (error) {
    // Do not log the message or recipient; Apps Script execution logs stay safe.
    console.error('Shoestagram mail send failed.');
    return shoestagramRelayResult_({ok: false, code: 'send_failed'});
  }
}
