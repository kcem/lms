<?php

/*
 *  LMS version 1.11-git
 *
 *  (C) Copyright 2001-2020 LMS Developers
 *
 *  Please, see the doc/AUTHORS for more information about authors!
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License Version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307,
 *  USA.
 *
 *  $Id$
 */

function parse_notification_mail($string, $data)
{
    $customerinfo = $data['customerinfo'];
    $string = str_replace('%cid%', $customerinfo['id'], $string);
    $string = str_replace('%customername%', $customerinfo['customername'], $string);
    $document = $data['document'];
    $string = str_replace('%docid%', $document['id'], $string);
    return $string;
}

function module_main()
{
    global $SESSION;

    $LMS = LMS::getInstance();
    $SMARTY = LMSSmarty::getInstance();

    if (isset($_POST['documentid']) && ($documentid = intval($_POST['documentid'])) > 0) {
        if ($LMS->DB->GetOne(
            'SELECT 1 FROM documents WHERE id = ? AND customerid = ? AND closed = 0 AND confirmdate > 0 AND confirmdate > ?NOW?',
            array($documentid, $SESSION->id)
        )) {
            $files = array();
            $error = null;

            if (isset($_FILES['files'])) {
                foreach ($_FILES['files']['name'] as $fileidx => $filename) {
                    if (!empty($filename)) {
                        if (is_uploaded_file($_FILES['files']['tmp_name'][$fileidx]) && $_FILES['files']['size'][$fileidx]) {
                            $files[] = array(
                                'tmpname' => null,
                                'filename' => $filename,
                                'name' => $_FILES['files']['tmp_name'][$fileidx],
                                'type' => $_FILES['files']['type'][$fileidx],
                                'md5sum' => md5($_FILES['files']['tmp_name'][$fileidx]),
                                'attachmenttype' => -1,
                            );
                        } else { // upload errors
                            if (isset($error['files'])) {
                                $error['files'] .= "\n";
                            } else {
                                $error['files'] = '';
                            }
                            switch ($_FILES['files']['error'][$fileidx]) {
                                case 1:
                                case 2:
                                    $error['files'] .= trans('File is too large: $a', $filename);
                                    break;
                                case 3:
                                    $error['files'] .= trans('File upload has finished prematurely: $a', $filename);
                                    break;
                                case 4:
                                    $error['files'] .= trans('Path to file was not specified: $a', $filename);
                                    break;
                                default:
                                    $error['files'] .= trans('Problem during file upload: $a', $filename);
                                    break;
                            }
                        }
                    }
                }
                if (!$error) {
                    $error = $LMS->AddDocumentFileAttachments($files);
                    if (!$error) {
                        $attachmentids = $LMS->AddDocumentScans($documentid, $files);
                        if ($attachmentids) {
                            $mail_sender = ConfigHelper::getConfig('userpanel.signed_document_scan_operator_notification_mail_sender', ConfigHelper::getConfig('mail.smtp_username'));
                            $mail_recipient = ConfigHelper::getConfig('userpanel.signed_document_scan_operator_notification_mail_recipient');
                            $mail_format = ConfigHelper::getConfig('userpanel.signed_document_scan_operator_notification_mail_format', 'text');
                            $mail_subject = ConfigHelper::getConfig('userpanel.signed_document_scan_operator_notification_mail_subject');
                            $mail_body = ConfigHelper::getConfig('userpanel.signed_document_scan_operator_notification_mail_body');

                            if (!empty($mail_sender)) {
                                $customerinfo = $LMS->GetCustomer($SESSION->id);

                                if (!empty($mail_recipient) && !empty($mail_subject) && !empty($mail_body)) {
                                    // operator notification
                                    $mail_subject = parse_notification_mail(
                                        $mail_subject,
                                        array(
                                            'customerinfo' => $customerinfo,
                                            'document' => array(
                                                'id' => $documentid,
                                                'attachmentids' => $attachmentids,
                                            ),
                                        )
                                    );
                                    $mail_body = parse_notification_mail(
                                        $mail_body,
                                        array(
                                            'customerinfo' => $customerinfo,
                                            'document' => array(
                                                'id' => $documentid,
                                                'attachmentids' => $attachmentids,
                                            ),
                                        )
                                    );
                                    $LMS->SendMail($mail_recipient, array(
                                        'From' => $mail_sender,
                                        'To' => $mail_recipient,
                                        'Subject' => $mail_subject,
                                        'X-LMS-Format' => $mail_format,
                                    ), $mail_body);
                                }

                                $mail_format = ConfigHelper::getConfig('userpanel.signed_document_scan_customer_notification_mail_format', 'text');
                                $mail_subject = ConfigHelper::getConfig('userpanel.signed_document_scan_customer_notification_mail_subject');
                                $mail_body = ConfigHelper::getConfig('userpanel.signed_document_scan_customer_notification_mail_body');
                                if (!empty($mail_recipient) && !empty($mail_subject) && !empty($mail_body)) {
                                    // customer notification
                                    $mail_subject = parse_notification_mail(
                                        $mail_subject,
                                        array(
                                            'customerinfo' => $customerinfo,
                                            'document' => array(
                                                'id' => $documentid,
                                                'attachmentids' => $attachmentids,
                                            ),
                                        )
                                    );
                                    $mail_body = parse_notification_mail(
                                        $mail_body,
                                        array(
                                            'customerinfo' => $customerinfo,
                                            'document' => array(
                                                'id' => $documentid,
                                                'attachmentids' => $attachmentids,
                                            ),
                                        )
                                    );
                                    $LMS->SendMail($mail_recipient, array(
                                        'From' => $mail_sender,
                                        'To' => $mail_recipient,
                                        'Subject' => $mail_subject,
                                        'X-LMS-Format' => $mail_format,
                                    ), $mail_body);
                                }
                            }
                        }
                    } else {
                        $SMARTY->assign('error', $error);
                    }
                } else {
                    $SMARTY->assign('error', $error);
                }
            }
        }
    }

    $documents = $LMS->DB->GetAll('SELECT d.id, d.number, d.type, c.title, c.fromdate, c.todate, 
		    c.description, n.template, d.closed, d.cdate, d.confirmdate
		FROM documentcontents c
		JOIN documents d ON (c.docid = d.id)
		LEFT JOIN numberplans n ON (d.numberplanid = n.id)
		WHERE d.customerid = ?'
            . (ConfigHelper::checkConfig('userpanel.show_confirmed_documents_only')
                ? ' AND (d.closed > 0 OR d.confirmdate >= ?NOW? OR d.confirmdate = -1)': '')
            . (ConfigHelper::checkConfig('userpanel.hide_archived_documents') ? ' AND d.archived = 0': '')
            . ' ORDER BY cdate', array($SESSION->id));

    if (!empty($documents)) {
        foreach ($documents as &$doc) {
            $doc['attachments'] = $LMS->DB->GetAllBykey('SELECT * FROM documentattachments WHERE docid = ?
				ORDER BY type DESC, filename', 'id', array($doc['id']));
        }
    }

    $unit_multipliers = array(
        'K' => 1024,
        'M' => 1024 * 1024,
        'G' => 1024 * 1024 * 1024,
        'T' => 1024 * 1024 * 1024 * 1024,
    );
    foreach (array('post_max_size', 'upload_max_filesize') as $var) {
        preg_match('/^(?<number>[0-9]+)(?<unit>[kKmMgGtT]?)$/', ini_get($var), $m);
        $unit_multiplier = isset($m['unit']) ? $unit_multipliers[strtoupper($m['unit'])] : 1;
        if ($var == 'post_max_size') {
            $unit_multiplier *= 1/1.33;
        }
        if (empty($m['number'])) {
            $val['bytes'] = 0;
            $val['text'] = trans('(unlimited)');
        } else {
            $val['bytes'] = round($m['number'] * $unit_multiplier);
            $res = setunits($val['bytes']);
            $val['text'] = round($res[0]) . ' ' . $res[1];
        }
        $SMARTY->assign($var, $val);
    }

    $SMARTY->assign('documents', $documents);
    $SMARTY->display('module:documents.html');
}

function module_docview()
{
    include 'docview.php';
}

if (defined('USERPANEL_SETUPMODE')) {
    function module_setup()
    {
        $SMARTY = LMSSmarty::getInstance();

        $SMARTY->assign(
            'moduleconfig',
            array(
                'hide_documentbox' => ConfigHelper::getConfig('userpanel.hide_documentbox'),
                'show_confirmed_documents_only' => ConfigHelper::checkConfig('userpanel.show_confirmed_documents_only'),
                'hide_archived_documents' => ConfigHelper::checkConfig('userpanel.hide_archived_documents'),
                'signed_document_scan_operator_notification_mail_sender' =>
                    ConfigHelper::getConfig('userpanel.signed_document_scan_operator_notification_mail_sender', '', true),
                'signed_document_scan_operator_notification_mail_recipient' =>
                    ConfigHelper::getConfig('userpanel.signed_document_scan_operator_notification_mail_recipient', '', true),
                'signed_document_scan_operator_notification_mail_format' =>
                    ConfigHelper::getConfig('userpanel.signed_document_scan_operator_notification_mail_format', 'text'),
                'signed_document_scan_operator_notification_mail_subject' =>
                    ConfigHelper::getConfig('userpanel.signed_document_scan_operator_notification_mail_subject', '', true),
                'signed_document_scan_operator_notification_mail_body' =>
                    ConfigHelper::getConfig('userpanel.signed_document_scan_operator_notification_mail_body', '', true),
                'signed_document_scan_customer_notification_mail_format' =>
                    ConfigHelper::getConfig('userpanel.signed_document_scan_customer_notification_mail_format', 'text'),
                'signed_document_scan_customer_notification_mail_subject' =>
                    ConfigHelper::getConfig('userpanel.signed_document_scan_customer_notification_mail_subject', '', true),
                'signed_document_scan_customer_notification_mail_body' =>
                    ConfigHelper::getConfig('userpanel.signed_document_scan_customer_notification_mail_body', '', true),
                'document_approval_customer_notification_mail_format' =>
                    ConfigHelper::getConfig('userpanel.document_approval_customer_notification_mail_format', 'text'),
                'document_approval_customer_notification_mail_subject' =>
                    ConfigHelper::getConfig('userpanel.document_approval_customer_notification_mail_subject', '', true),
                'document_approval_customer_notification_mail_body' =>
                    ConfigHelper::getConfig('userpanel.document_approval_customer_notification_mail_body', '', true),
            )
        );

        $SMARTY->display('module:documents:setup.html');
    }

    function module_submit_setup()
    {
        if (!isset($_POST['moduleconfig'])) {
            die;
        }

        $DB = LMSDB::getInstance();

        $variables = array(
            'hide_documentbox' => CONFIG_TYPE_BOOLEAN,
            'show_confirmed_documents_only' => CONFIG_TYPE_BOOLEAN,
            'hide_archived_documents' => CONFIG_TYPE_BOOLEAN,
            'signed_document_scan_operator_notification_mail_sender' => CONFIG_TYPE_RICHTEXT,
            'signed_document_scan_operator_notification_mail_recipient' => CONFIG_TYPE_RICHTEXT,
            'signed_document_scan_operator_notification_mail_format' => CONFIG_TYPE_NONE,
            'signed_document_scan_operator_notification_mail_subject' => CONFIG_TYPE_RICHTEXT,
            'signed_document_scan_operator_notification_mail_body' => CONFIG_TYPE_RICHTEXT,
            'signed_document_scan_customer_notification_mail_format' => CONFIG_TYPE_NONE,
            'signed_document_scan_customer_notification_mail_subject' => CONFIG_TYPE_RICHTEXT,
            'signed_document_scan_customer_notification_mail_body' => CONFIG_TYPE_RICHTEXT,
            'document_approval_customer_notification_mail_format' => CONFIG_TYPE_NONE,
            'document_approval_customer_notification_mail_subject' => CONFIG_TYPE_RICHTEXT,
            'document_approval_customer_notification_mail_body' => CONFIG_TYPE_RICHTEXT,
        );

        $moduleconfig = $_POST['moduleconfig'];

        foreach ($variables as $variable => $type) {
            switch ($type) {
                case CONFIG_TYPE_BOOLEAN:
                    $value = isset($moduleconfig[$variable]) ? 1 : 0;
                    break;
                case CONFIG_TYPE_RICHTEXT:
                    $value = $moduleconfig[$variable];
                    break;
                case CONFIG_TYPE_NONE:
                    $mail_format = str_replace('_mail_format', '_mail_body', $variable);
                    if (isset($moduleconfig['wysiwyg'][$mail_format]) && $moduleconfig['wysiwyg'][$mail_format] == 'true') {
                        $value = 'html';
                    } else {
                        $value = 'text';
                    }
                    break;
            }
            $DB->Execute(
                'UPDATE uiconfig SET value = ? WHERE section = ? AND var = ?',
                array($value, 'userpanel', $variable,)
            );
        }

        header('Location: ?m=userpanel&module=documents');
    }
}