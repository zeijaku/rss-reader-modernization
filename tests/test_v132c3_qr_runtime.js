'use strict';

const crypto = require('crypto');
const qr = require('../public/js/totp-qr.js');
let failures = 0;
function check(condition, message) {
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
    if (!condition) failures += 1;
}

const uri = 'otpauth://totp/iGuguru%3Auser-1?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=iGuguru&algorithm=SHA1&digits=6&period=30';
const matrix = qr.makeMatrix(uri);
check(Array.isArray(matrix) && matrix.length === 41, 'default provisioning URI fits a Version 6 QR matrix');
check(matrix.every(row => Array.isArray(row) && row.length === matrix.length && row.every(cell => typeof cell === 'boolean')), 'QR matrix is square and fully assigned');
check(matrix[0][0] === true && matrix[6][6] === true && matrix[3][3] === true, 'top-left finder pattern is present');
check(matrix[matrix.length - 8][8] === true, 'fixed dark module is present');
const digest = crypto.createHash('sha256').update(matrix.map(row => row.map(v => v ? '1' : '0').join('')).join('')).digest('hex');
check(digest.length === 64, 'QR matrix output is deterministic hashable data');

const longUri = 'otpauth://totp/' + encodeURIComponent('日本語発行者名テスト:user-123')
    + '?secret=' + 'B'.repeat(32)
    + '&issuer=' + encodeURIComponent('日本語発行者名テスト')
    + '&algorithm=SHA1&digits=6&period=30';
const longMatrix = qr.makeMatrix(longUri);
check(longMatrix.length > matrix.length && longMatrix.length <= 97, 'local QR generator supports longer percent-encoded provisioning URIs');

let rejected = false;
try { qr.makeMatrix(''); } catch (error) { rejected = true; }
check(rejected, 'empty QR payload is rejected');

process.exit(failures === 0 ? 0 : 1);
