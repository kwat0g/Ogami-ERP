# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-recruitment.process.spec.ts >> HR02-HAPPY — candidate applies through the posting lifecycle and HR hires through interview and offer
- Location: e2e-process/hr-recruitment.process.spec.ts:260:1

# Error details

```
Error: Interview scheduled: E2E 26817554ad4e Quality Engineer email should contain Asia/Manila

expect(received).toContain(expected) // indexOf

Expected substring: "Asia/Manila"
Received string:    "{\"ID\":\"7PvNztOa23TtFLOxUGwKbg\",\"MessageID\":\"b18ebb0870268e4ac13ca27e2b0975dc@ogami-e2e.test\",\"From\":{\"Name\":\"Ogami E2E\",\"Address\":\"e2e@ogami-e2e.test\"},\"To\":[{\"Name\":\"\",\"Address\":\"candidate-26817554ad4e@candidate.test\"}],\"Cc\":[],\"Bcc\":[],\"ReplyTo\":[{\"Name\":\"\",\"Address\":\"e2e@ogami-e2e.test\"}],\"ReturnPath\":\"e2e@ogami-e2e.test\",\"Subject\":\"[TESTING] [Philippine Ogami Corporation] Interview scheduled: E2E 26817554ad4e Quality Engineer\",\"ListUnsubscribe\":{\"Header\":\"\",\"Links\":[],\"Errors\":\"\",\"HeaderPost\":\"\"},\"Date\":\"2026-11-05T04:24:00+08:00\",\"Tags\":[],\"Username\":\"\",\"Text\":\"Philippine Ogami Corporation\\r\\n\\r\\n# Interview scheduled\\r\\n\\r\\nHello E2E Candidate 26817554ad4e,\\r\\n\\r\\nAn interview has been scheduled for your **E2E 26817554ad4e Quality Engineer** application.\\r\\n\\r\\nDate and time: **November 6, 2026 8:23 PM**\\r\\nLocation or meeting details: Quality room 26817554ad4e\\r\\nInterviewer: E2E Interviewer 26817554ad4e\\r\\n\\r\\nYour tracking code: **RCT-EBE7XF**\\r\\n\\r\\nView application status: https://nginx/careers/track\\r\\n\\r\\nRegards,\\r\\nPhilippine Ogami Corporation Recruitment Team\\r\\n\\r\\nYou received this email because an interview was scheduled for your application. Contact the recruitment team if you cannot attend.\\r\\n\\r\\nPhilippine Ogami Corporation\\r\\nPhilippine Ogami Corporation\\r\\nBlock 2, Lots 1 & 2, First Cavite Industrial Estate (FCIE), Barangay Langkaan 1, Dasmariñas, Cavite, Philippines\\r\\nAutomated message.\\r\\n\",\"HTML\":\"<!DOCTYPE html PUBLIC \\\"-//W3C//DTD XHTML 1.0 Transitional//EN\\\" \\\"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\\\">\\r\\n<html xmlns=\\\"http://www.w3.org/1999/xhtml\\\" lang=\\\"en\\\" dir=\\\"ltr\\\">\\r\\n<head>\\r\\n<title>Interview scheduled</title>\\r\\n<meta name=\\\"viewport\\\" content=\\\"width=device-width, initial-scale=1.0\\\">\\r\\n<meta http-equiv=\\\"Content-Type\\\" content=\\\"text/html; charset=UTF-8\\\">\\r\\n<meta name=\\\"color-scheme\\\" content=\\\"light dark\\\">\\r\\n<meta name=\\\"supported-color-schemes\\\" content=\\\"light dark\\\">\\r\\n<style>\\r\\nbody, body *:not(html):not(style):not(br):not(tr):not(code) {\\r\\nfont-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';\\r\\n}\\r\\nbody { background-color: #fdfcfa; color: #4a4239; }\\r\\n.wrapper, .body { background-color: #fdfcfa; }\\r\\n.inner-body { width: 100% !important; max-width: 600px !important; background-color: #ffffff; border: 1px solid #e8e2d8; border-radius: 14px; box-shadow: 0 8px 24px rgba(31, 27, 22, 0.07); }\\r\\n.content-cell { padding: 38px 42px 36px !important; }\\r\\nh1 { color: #1f1b16; font-size: 24px !important; letter-spacing: -0.02em; line-height: 1.25 !important; margin-bottom: 20px !important; }\\r\\nh2 { color: #4a4239; font-size: 16px !important; margin-top: 28px !important; }\\r\\np, li { color: #4a4239; font-size: 15px !important; line-height: 1.65 !important; }\\r\\na { color: #b4542a; }\\r\\n.table table, .content-cell > table:not(.action) { width: 100% !important; margin: 24px 0 !important; border: 1px solid #e8e2d8; border-collapse: collapse; }\\r\\n.table th, .content-cell > table:not(.action) th { background-color: #f7f4ef; color: #6b6259; font-size: 11px !important; letter-spacing: 0.06em; padding: 11px 14px !important; text-transform: uppercase; }\\r\\n.table td, .content-cell > table:not(.action) td { color: #4a4239; font-size: 14px !important; line-height: 1.5 !important; padding: 11px 14px !important; border-bottom: 1px solid #f0eae0; }\\r\\n.table tr:last-child td, .content-cell > table:not(.action) tr:last-child td { border-bottom: 0; }\\r\\n.action { margin: 30px auto !important; }\\r\\n.button-primary, .button-blue { background-color: #b4542a !important; border-color: #b4542a !important; border-radius: 7px !important; font-size: 14px !important; font-weight: 700 !important; letter-spacing: 0.01em; }\\r\\n.panel { border-left-color: #b4542a !important; border-radius: 0 9px 9px 0; }\\r\\n.panel-content { background-color: #f7f4ef; color: #4a4239; padding: 16px 18px !important; }\\r\\n.panel-content p { color: #4a4239; }\\r\\n.footer { width: 100% !important; max-width: 600px !important; }\\r\\n@media (prefers-color-scheme: dark) {\\r\\nbody, .wrapper, .body { background-color: #17140f !important; color: #d6cec1 !important; }\\r\\n.inner-body, .header { background-color: #1f1b16 !important; border-color: #332c24 !important; }\\r\\n.brand-name { color: #f5f1ea !important; }\\r\\n.brand-tagline { color: #a89f93 !important; }\\r\\n.logo-light { display: none !important; }\\r\\n.logo-dark { display: block !important; }\\r\\n.logo-backing { background-color: transparent !important; }\\r\\nh1, h2, p, li, .table td, .content-cell > table:not(.action) td { color: #d6cec1 !important; }\\r\\n.table th, .content-cell > table:not(.action) th, .panel-content { background-color: #2a251e !important; color: #d6cec1 !important; }\\r\\n.table table, .content-cell > table:not(.action) { border-color: #332c24 !important; }\\r\\n.table td, .content-cell > table:not(.action) td { border-bottom-color: #241f19 !important; }\\r\\n.content a { color: #d97848 !important; }\\r\\n.content .action a { color: #ffffff !important; }\\r\\n.footer, .footer td, .footer div, .footer a { color: #a89f93 !important; }\\r\\n.footer .footer-brand-name { color: #f5f1ea !important; }\\r\\n.footer .footer-divider { background-color: #332c24 !important; }\\r\\n}\\r\\n@media only screen and (max-width: 640px) {\\r\\n.content { table-layout: fixed !important; }\\r\\n.inner-body, .footer { width: 100% !important; max-width: 100% !important; }\\r\\n.content-cell { padding: 30px 24px 28px !important; }\\r\\n.content-cell > table:not(.action) { table-layout: fixed !important; }\\r\\n.content-cell > table:not(.action) th, .content-cell > table:not(.action) td { padding: 8px 6px !important; overflow-wrap: anywhere; }\\r\\n}\\r\\n@media only screen and (max-width: 500px) {\\r\\n.button { width: 100% !important; text-align: center !important; }\\r\\n}\\r\\n</style>\\r\\n\\r\\n</head>\\r\\n<body style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; -webkit-text-size-adjust: none; background-color: #ffffff; color: #52525b; height: 100%; line-height: 1.4; margin: 0; padding: 0; width: 100% !important;\\\">\\r\\n<div style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; display: none; max-height: 0; overflow: hidden; opacity: 0; color: transparent; mso-hide: all;\\\">Interview details for E2E 26817554ad4e Quality Engineer.</div>\\r\\n\\r\\n<table class=\\\"wrapper\\\" width=\\\"100%\\\" cellpadding=\\\"0\\\" cellspacing=\\\"0\\\" role=\\\"presentation\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; -premailer-cellpadding: 0; -premailer-cellspacing: 0; -premailer-width: 100%; background-color: #fafafa; margin: 0; padding: 0; width: 100%;\\\">\\r\\n<tr>\\r\\n<td align=\\\"center\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative;\\\">\\r\\n<table class=\\\"content\\\" width=\\\"100%\\\" cellpadding=\\\"0\\\" cellspacing=\\\"0\\\" role=\\\"presentation\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; -premailer-cellpadding: 0; -premailer-cellspacing: 0; -premailer-width: 100%; margin: 0; padding: 0; width: 100%;\\\">\\r\\n<tr>\\r\\n<td style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; height: 5px; background-color: #b4542a; font-size: 0; line-height: 0;\\\"> </td>\\r\\n</tr>\\r\\n<tr>\\r\\n<td class=\\\"header\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; padding: 26px 32px 24px; background-color: #ffffff; text-align: center;\\\">\\r\\n<a href=\\\"https://nginx\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; font-size: 19px; font-weight: bold; display: inline-block; color: #1f1b16; text-decoration: none;\\\">\\r\\n</a><table cellpadding=\\\"0\\\" cellspacing=\\\"0\\\" role=\\\"presentation\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; margin: 0 auto;\\\">\\r\\n<tr>\\r\\n<td valign=\\\"middle\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; padding-right: 12px;\\\">\\r\\n<table class=\\\"logo-backing\\\" cellpadding=\\\"0\\\" cellspacing=\\\"0\\\" role=\\\"presentation\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; background-color: #ffffff;\\\">\\r\\n<tr><td style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; padding: 3px;\\\">\\r\\n<img class=\\\"logo-light\\\" src=\\\"cid:ogami-logo@ogami\\\" alt=\\\"Philippine Ogami Corporation logo\\\" width=\\\"64\\\" height=\\\"64\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; max-width: 100%; display: block; width: 64px; height: 64px; border: 0;\\\">\\r\\n<img class=\\\"logo-dark\\\" src=\\\"cid:ogami-logo-dark@ogami\\\" alt=\\\"Philippine Ogami Corporation logo\\\" width=\\\"64\\\" height=\\\"64\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; max-width: 100%; display: none; width: 64px; height: 64px; border: 0;\\\">\\r\\n</td></tr>\\r\\n</table>\\r\\n</td>\\r\\n<td valign=\\\"middle\\\" align=\\\"left\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative;\\\">\\r\\n<div class=\\\"brand-name\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; color: #1f1b16; font-size: 20px; font-weight: 800; letter-spacing: -0.02em; line-height: 1.15;\\\">Philippine Ogami Corporation</div>\\r\\n<div class=\\\"brand-tagline\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; margin-top: 4px; color: #6b6259; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; line-height: 1.3; text-transform: uppercase;\\\">Business portal</div>\\r\\n</td>\\r\\n</tr>\\r\\n</table>\\r\\n\\r\\n</td>\\r\\n</tr>\\r\\n\\r\\n<!-- Email Body -->\\r\\n<tr>\\r\\n<td class=\\\"body\\\" width=\\\"100%\\\" cellpadding=\\\"0\\\" cellspacing=\\\"0\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; -premailer-cellpadding: 0; -premailer-cellspacing: 0; -premailer-width: 100%; background-color: #fafafa; border-bottom: 1px solid #fafafa; border-top: 1px solid #fafafa; margin: 0; padding: 0; width: 100%; border: hidden !important;\\\">\\r\\n<table class=\\\"inner-body\\\" align=\\\"center\\\" width=\\\"600\\\" cellpadding=\\\"0\\\" cellspacing=\\\"0\\\" role=\\\"presentation\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; width: 100% !important; max-width: 600px !important; border: 1px solid #e8e2d8; -premailer-cellpadding: 0; -premailer-cellspacing: 0; -premailer-width: 570px; background-color: #ffffff; border-color: #e4e4e7; border-radius: 4px; border-width: 1px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1); margin: 0 auto; padding: 0;\\\">\\r\\n<!-- Body content -->\\r\\n<tr>\\r\\n<td class=\\\"content-cell\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; padding: 38px 42px 36px !important; max-width: 100vw;\\\">\\r\\n<h1 style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; font-size: 24px !important; letter-spacing: -0.02em; line-height: 1.25 !important; margin-bottom: 20px !important; color: #18181b; font-weight: bold; margin-top: 0; text-align: start;\\\">Interview scheduled</h1>\\r\\n<p style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; color: #4a4239; font-size: 15px !important; line-height: 1.65 !important; margin-top: 0; text-align: left;\\\">Hello E2E Candidate 26817554ad4e,</p>\\r\\n<p style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; color: #4a4239; font-size: 15px !important; line-height: 1.65 !important; margin-top: 0; text-align: left;\\\">An interview has been scheduled for your <strong style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative;\\\">E2E 26817554ad4e Quality Engineer</strong> application.</p>\\r\\n<table class=\\\"panel\\\" width=\\\"100%\\\" cellpadding=\\\"0\\\" cellspacing=\\\"0\\\" role=\\\"presentation\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; border-left-color: #b4542a !important; border-radius: 0 9px 9px 0; border-left: #18181b solid 4px; width: 100% !important; margin: 24px 0 !important; border: 1px solid #e8e2d8; border-collapse: collapse;\\\">\\r\\n<tr>\\r\\n<td class=\\\"panel-content\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; background-color: #fafafa; color: #4a4239; font-size: 14px !important; line-height: 1.5 !important; padding: 11px 14px !important; border-bottom: 0;\\\">\\r\\n<table width=\\\"100%\\\" cellpadding=\\\"0\\\" cellspacing=\\\"0\\\" role=\\\"presentation\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative;\\\">\\r\\n<tr>\\r\\n<td class=\\\"panel-item\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; color: #4a4239; font-size: 14px !important; line-height: 1.5 !important; padding: 11px 14px !important; border-bottom: 0;\\\">\\r\\n<p style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; font-size: 15px !important; line-height: 1.65 !important; margin-top: 0; text-align: left; color: #52525b; margin-bottom: 0; padding-bottom: 0;\\\">Date and time: <strong style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative;\\\">November 6, 2026 8:23 PM</strong><br>\\r\\nLocation or meeting details: Quality room 26817554ad4e<br>\\r\\nInterviewer: E2E Interviewer 26817554ad4e</p>\\r\\n</td>\\r\\n</tr>\\r\\n</table>\\r\\n</td>\\r\\n</tr>\\r\\n</table>\\r\\n<p style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; color: #4a4239; font-size: 15px !important; line-height: 1.65 !important; margin-top: 0; text-align: left;\\\">Your tracking code: <strong style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative;\\\">RCT-EBE7XF</strong></p>\\r\\n<table class=\\\"action\\\" align=\\\"center\\\" width=\\\"100%\\\" cellpadding=\\\"0\\\" cellspacing=\\\"0\\\" role=\\\"presentation\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; margin: 30px auto !important; -premailer-cellpadding: 0; -premailer-cellspacing: 0; -premailer-width: 100%; padding: 0; text-align: center; width: 100%; float: unset;\\\">\\r\\n<tr>\\r\\n<td align=\\\"center\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; padding: 0 0 22px;\\\">\\r\\n<!--[if mso]>\\r\\n<v:roundrect xmlns:v=\\\"urn:schemas-microsoft-com:vml\\\" xmlns:w=\\\"urn:schemas-microsoft-com:office:word\\\" href=\\\"https://nginx/careers/track\\\" style=\\\"height:48px;v-text-anchor:middle;width:280px;\\\" arcsize=\\\"12%\\\" stroke=\\\"f\\\" fillcolor=\\\"#b4542a\\\">\\r\\n<w:anchorlock/>\\r\\n<center style=\\\"color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;\\\">View application status</center>\\r\\n</v:roundrect>\\r\\n<![endif]-->\\r\\n<!--[if !mso]><!-- -->\\r\\n<a href=\\\"https://nginx/careers/track\\\" target=\\\"_blank\\\" rel=\\\"noopener\\\" style=\\\"box-sizing: border-box; position: relative; word-break: break-all; display: inline-block; min-width: 200px; min-height: 48px; padding: 0 22px; border-radius: 7px; background-color: #b4542a; color: #ffffff; font-family: Arial,sans-serif; font-size: 16px; font-weight: 700; line-height: 48px; text-align: center; text-decoration: none;\\\">View application status</a>\\r\\n<!--<![endif]-->\\r\\n<p style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; color: #4a4239; margin-top: 0; margin: 12px 0 0; text-align: center; font-size: 13px; line-height: 20px;\\\"><a href=\\\"https://nginx/careers/track\\\" target=\\\"_blank\\\" rel=\\\"noopener\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; word-break: break-all; color: #8c3c1e; text-decoration: underline;\\\">Open this link if the button does not work</a></p>\\r\\n</td>\\r\\n</tr>\\r\\n</table>\\r\\n<p style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; color: #4a4239; font-size: 15px !important; line-height: 1.65 !important; margin-top: 0; text-align: left;\\\">Regards,<br>\\r\\nPhilippine Ogami Corporation Recruitment Team</p>\\r\\n<p style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; color: #4a4239; font-size: 15px !important; line-height: 1.65 !important; margin-top: 0; text-align: left;\\\">You received this email because an interview was scheduled for your application. Contact the recruitment team if you cannot attend.</p>\\r\\n\\r\\n\\r\\n\\r\\n</td>\\r\\n</tr>\\r\\n</table>\\r\\n</td>\\r\\n</tr>\\r\\n\\r\\n<tr>\\r\\n<td style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative;\\\">\\r\\n<table class=\\\"footer\\\" align=\\\"center\\\" width=\\\"570\\\" cellpadding=\\\"0\\\" cellspacing=\\\"0\\\" role=\\\"presentation\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; width: 100% !important; max-width: 600px !important; -premailer-cellpadding: 0; -premailer-cellspacing: 0; -premailer-width: 570px; padding: 0; margin: 0 auto; text-align: center;\\\">\\r\\n<tr>\\r\\n<td class=\\\"content-cell\\\" align=\\\"center\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; max-width: 100vw; padding: 26px 32px 36px; color: #6b6259; font-size: 12px; line-height: 1.65;\\\">\\r\\n<div class=\\\"footer-divider\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; height: 1px; margin: 0 auto 22px; background-color: #e8e2d8; font-size: 0; line-height: 0;\\\"> </div>\\r\\n<div class=\\\"footer-brand-name\\\" style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; color: #4a4239; font-size: 13px; font-weight: 800;\\\">Philippine Ogami Corporation</div>\\r\\n<div style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; margin-top: 2px;\\\">Philippine Ogami Corporation</div>\\r\\n<div style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; margin-top: 2px;\\\">Block 2, Lots 1 &amp; 2, First Cavite Industrial Estate (FCIE), Barangay Langkaan 1, Dasmariñas, Cavite, Philippines</div>\\r\\n<div style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative;\\\">\\r\\n</div>\\r\\n<div style=\\\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; position: relative; margin-top: 12px; color: #6b6259;\\\">This is an automated message.</div>\\r\\n</td>\\r\\n</tr>\\r\\n</table>\\r\\n</td>\\r\\n</tr>\\r\\n</table>\\r\\n</td>\\r\\n</tr>\\r\\n</table>\\r\\n</body>\\r\\n</html>\",\"Size\":172846,\"Inline\":[{\"PartID\":\"2\",\"FileName\":\"ogami-logo.png\",\"ContentType\":\"image/png\",\"ContentID\":\"ogami-logo@ogami\",\"Size\":64076,\"Checksums\":{\"MD5\":\"ebe6fc5e50ee253ea3009c321a0dcbae\",\"SHA1\":\"a838d2bee8e75610686a7567aec8be6447d88982\",\"SHA256\":\"0a2b0b57932f0b93324c3a19b92fd7931382c1cbbd0d9fc3009466ea575b9d44\"}},{\"PartID\":\"3\",\"FileName\":\"ogami-mark-dark.png\",\"ContentType\":\"image/png\",\"ContentID\":\"ogami-logo-dark@ogami\",\"Size\":43509,\"Checksums\":{\"MD5\":\"992380b2bf0eb4c063c252e34c42228a\",\"SHA1\":\"1ce2adb7c895b48d991612e10e387b722767e769\",\"SHA256\":\"f9f3c93014a0b8b9618572114d79bc616005977c0071afb2b58d150f4f3eb74e\"}}],\"Attachments\":[]}"
```

# Test source

```ts
  140 |            (SELECT COUNT(*)::int FROM job_applications application WHERE application.job_posting_id = posting.id) AS application_count,
  141 |            COALESCE((
  142 |              SELECT json_agg(json_build_object(
  143 |                'action', audit.action,
  144 |                'actor_email', actor.email,
  145 |                'status', audit.new_values->>'status'
  146 |              ) ORDER BY audit.id)
  147 |                FROM audit_logs audit
  148 |                LEFT JOIN users actor ON actor.id = audit.user_id
  149 |               WHERE audit.model_type ILIKE '%JobPosting%'
  150 |                 AND audit.model_id = posting.id
  151 |            ), '[]'::json) AS audit_entries
  152 |       FROM job_postings posting
  153 |       JOIN users creator ON creator.id = posting.created_by
  154 |      WHERE posting.title = ${sqlText(title)}
  155 |   `);
  156 | }
  157 | 
  158 | async function applicationSnapshot(email: string): Promise<ApplicationSnapshot[]> {
  159 |   return readOnlyDatabaseJson<ApplicationSnapshot[]>(`
  160 |     SELECT application.id,
  161 |            application.application_number,
  162 |            application.tracking_code,
  163 |            application.stage,
  164 |            application.email,
  165 |            application.resume_original_name,
  166 |            application.consent_at::text AS consent_at,
  167 |            application.converted_employee_id,
  168 |            posting.status AS posting_status,
  169 |            COALESCE((
  170 |              SELECT json_agg(json_build_object(
  171 |                'event_type', event.event_type,
  172 |                'from_stage', event.from_stage,
  173 |                'to_stage', event.to_stage,
  174 |                'actor_email', actor.email
  175 |              ) ORDER BY event.id)
  176 |                FROM recruitment_application_events event
  177 |                LEFT JOIN users actor ON actor.id = event.actor_user_id
  178 |               WHERE event.job_application_id = application.id
  179 |            ), '[]'::json) AS events,
  180 |            COALESCE((
  181 |              SELECT json_agg(json_build_object(
  182 |                'interviewer_name', interview.interviewer_name,
  183 |                'location', interview.location,
  184 |                'outcome', interview.outcome,
  185 |                'created_by_email', creator.email
  186 |              ) ORDER BY interview.id)
  187 |                FROM application_interviews interview
  188 |                JOIN users creator ON creator.id = interview.created_by
  189 |               WHERE interview.job_application_id = application.id
  190 |            ), '[]'::json) AS interviews
  191 |       FROM job_applications application
  192 |       JOIN job_postings posting ON posting.id = application.job_posting_id
  193 |      WHERE application.email = ${sqlText(email)}
  194 |   `);
  195 | }
  196 | 
  197 | async function applicationNotifications(hashId: string): Promise<NotificationSnapshot[]> {
  198 |   return readOnlyDatabaseJson<NotificationSnapshot[]>(`
  199 |     SELECT recipient.email,
  200 |            role.slug AS role_slug,
  201 |            notice.data->>'link_to' AS link_to,
  202 |            notice.read_at::text AS read_at
  203 |       FROM notifications notice
  204 |       JOIN users recipient
  205 |         ON notice.notifiable_type = 'App\\Modules\\Auth\\Models\\User'
  206 |        AND recipient.id = notice.notifiable_id
  207 |       JOIN roles role ON role.id = recipient.role_id
  208 |      WHERE notice.type = 'recruitment.new_application'
  209 |        AND notice.data->>'entity_id' = ${sqlText(hashId)}
  210 |   `);
  211 | }
  212 | 
  213 | async function waitForApplication(email: string, expectedStage: string): Promise<ApplicationSnapshot> {
  214 |   let rows: ApplicationSnapshot[] = [];
  215 |   await expect.poll(async () => {
  216 |     rows = await applicationSnapshot(email);
  217 |     return rows[0]?.stage ?? 'missing';
  218 |   }, { timeout: 15_000, message: `candidate application should reach ${expectedStage}` }).toBe(expectedStage);
  219 |   if (!rows[0]) throw new Error(`Application for ${email} disappeared from the E2E database.`);
  220 |   return rows[0];
  221 | }
  222 | 
  223 | async function newCandidateMail(
  224 |   email: string,
  225 |   excludedIds: Set<string>,
  226 |   subjectFragment: string,
  227 |   expectedBody: string[],
  228 | ): Promise<MailpitSummary> {
  229 |   let matching: MailpitSummary[] = [];
  230 |   await expect.poll(async () => {
  231 |     matching = (await findMailpitMessagesFor(email)).filter(
  232 |       (message) => !excludedIds.has(message.ID) && message.Subject.includes(subjectFragment),
  233 |     );
  234 |     return matching.length;
  235 |   }, { timeout: 25_000, message: `Mailpit should receive ${subjectFragment} for ${email}` }).toBeGreaterThan(0);
  236 | 
  237 |   const message = matching.sort((left, right) => right.Created.localeCompare(left.Created))[0];
  238 |   assertRecipientsExactly(message.To.map((recipient) => recipient.Address), [email]);
  239 |   const contents = JSON.stringify(await getMailpitMessage(message.ID));
> 240 |   for (const expected of expectedBody) expect(contents, `${subjectFragment} email should contain ${expected}`).toContain(expected);
      |                                                                                                                ^ Error: Interview scheduled: E2E 26817554ad4e Quality Engineer email should contain Asia/Manila
  241 |   return message;
  242 | }
  243 | 
  244 | async function existingMailIds(email: string): Promise<Set<string>> {
  245 |   return new Set((await findMailpitMessagesFor(email)).map((message) => message.ID));
  246 | }
  247 | 
  248 | async function assertStageEvent(email: string, from: string, to: string, actorEmail: string): Promise<void> {
  249 |   const application = await waitForApplication(email, to);
  250 |   const event = application.events.find((entry) => entry.event_type === 'stage.advanced' && entry.from_stage === from && entry.to_stage === to);
  251 |   expect(event, `stage event ${from} → ${to} should be persisted`).toBeTruthy();
  252 |   expect(event?.actor_email).toBe(actorEmail);
  253 | }
  254 | 
  255 | async function assertLiveStage(observer: ActorPage, expected: string, availableAction?: string): Promise<void> {
  256 |   await expect(statusBadge(observer.page, expected), `the open observer record should update to ${expected} without refresh`).toBeVisible();
  257 |   if (availableAction) await expect(observer.page.getByRole('button', { name: availableAction, exact: true })).toBeEnabled();
  258 | }
  259 | 
  260 | test('HR02-HAPPY — candidate applies through the posting lifecycle and HR hires through interview and offer', async ({ browser, request }, testInfo) => {
  261 |   test.setTimeout(240_000);
  262 | 
  263 |   const actors = await loadActors();
  264 |   const hrActor = actorByName(actors, 'hr_officer');
  265 |   const adminActor = actorByName(actors, 'system_admin');
  266 |   const clockNow = await readClock(request);
  267 |   const runId = randomUUID().replaceAll('-', '').slice(0, 12).toLowerCase();
  268 |   const postingTitle = `E2E ${runId} Quality Engineer`;
  269 |   const requirement = `Run ${runId}: injection molding quality experience`;
  270 |   const candidateName = `E2E Candidate ${runId}`;
  271 |   const firstName = 'E2E';
  272 |   const lastName = `Candidate ${runId}`;
  273 |   const candidateEmail = `candidate-${runId}@candidate.test`;
  274 |   const phone = '09171234567';
  275 |   const resumeName = `resume-${runId}.pdf`;
  276 |   const interviewerName = `E2E Interviewer ${runId}`;
  277 |   const interviewLocation = `Quality room ${runId}`;
  278 |   const interviewAt = manilaDateTimeInput(new Date(Date.parse(clockNow) + 48 * 60 * 60 * 1000).toISOString());
  279 |   const journey = createJourneyContext('HR02-HAPPY');
  280 |   journey.values.runId = runId;
  281 |   journey.values.postingTitle = postingTitle;
  282 |   journey.values.candidateEmail = candidateEmail;
  283 |   journey.values.candidateName = candidateName;
  284 | 
  285 |   const hr = await actorPage(browser, hrActor.actor, { timezoneId: 'Asia/Manila' });
  286 |   const observer = await actorPage(browser, adminActor.actor, { timezoneId: 'Asia/Manila' });
  287 |   const applicantContext = await browser.newContext({
  288 |     baseURL: process.env.E2E_BASE_URL,
  289 |     ignoreHTTPSErrors: true,
  290 |     timezoneId: 'Asia/Manila',
  291 |   });
  292 |   const applicantPage = await applicantContext.newPage();
  293 |   const applicantDiagnostics = await monitorPage(applicantPage);
  294 |   const primaryMailIdsBefore = await existingMailIds(candidateEmail);
  295 |   let primaryError: unknown;
  296 | 
  297 |   try {
  298 |     await hr.context.clock.setFixedTime(new Date(clockNow));
  299 |     await observer.context.clock.setFixedTime(new Date(clockNow));
  300 |     await applicantContext.clock.setFixedTime(new Date(clockNow));
  301 | 
  302 |     await runProcessStep({
  303 |       ...plannedSteps[0],
  304 |       page: hr.page,
  305 |       plannedSteps,
  306 |       context: journey,
  307 |       testInfo,
  308 |       action: async () => {
  309 |         await hr.page.goto('/dashboard');
  310 |         await expect(hr.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  311 |         await openSidebarItem(hr, 'Human Resources', '/hr/recruitment', 'Recruitment');
  312 |         await expect(hr.page.getByRole('heading', { name: 'Recruitment', exact: true })).toBeVisible();
  313 |         await hr.page.locator('[data-layout="page-header"]').getByRole('button', { name: 'New Posting', exact: true }).click();
  314 |         await expect(hr.page.getByRole('heading', { name: 'Create Job Posting', exact: true })).toBeVisible();
  315 |         await hr.page.getByLabel('Job Title').fill(postingTitle);
  316 |         await hr.page.getByRole('combobox', { name: 'Department' }).click();
  317 |         await hr.page.getByRole('option', { name: 'Human Resources', exact: true }).click();
  318 |         await hr.page.getByLabel('Employment Type').selectOption('regular');
  319 |         await hr.page.getByLabel('Slots').fill('1');
  320 |         await hr.page.getByPlaceholder('Describe the role, responsibilities, and day-to-day work...').fill(`Quality role for the E2E recruitment process ${runId}.`);
  321 |         await hr.page.getByPlaceholder('Type and press Enter to add').fill(requirement);
  322 |         await hr.page.getByRole('button', { name: 'Add', exact: true }).click();
  323 |         await hr.page.getByRole('button', { name: 'Create Posting', exact: true }).click();
  324 |         await expect(hr.page.getByText(postingTitle, { exact: true })).toBeVisible();
  325 |         await expect(statusBadge(hr.page, 'Draft')).toBeVisible();
  326 |       },
  327 |       verify: async () => {
  328 |         const rows = await postingSnapshot(postingTitle);
  329 |         expect(rows).toHaveLength(1);
  330 |         expect(rows[0].status).toBe('draft');
  331 |         expect(rows[0].slots).toBe(1);
  332 |         expect(rows[0].created_by_email).toBe(hrActor.email);
  333 |         expect(rows[0].posting_number).toMatch(/.+/);
  334 |         expect(rows[0].audit_entries.some((entry) => entry.actor_email === hrActor.email && entry.action === 'created')).toBe(true);
  335 |         journey.values.postingNumber = rows[0].posting_number;
  336 |         journey.values.postingId = rows[0].id;
  337 |       },
  338 |       assertInvariants: async () => hr.diagnostics.assertClean('HR draft posting creation'),
  339 |     });
  340 | 
```