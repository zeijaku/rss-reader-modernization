(function (root) {
    'use strict';

    var RS_BLOCKS_L = {
        1: [[1, 26, 19]],
        2: [[1, 44, 34]],
        3: [[1, 70, 55]],
        4: [[1, 100, 80]],
        5: [[1, 134, 108]],
        6: [[2, 86, 68]],
        7: [[2, 98, 78]],
        8: [[2, 121, 97]],
        9: [[2, 146, 116]],
        10: [[2, 86, 68], [2, 87, 69]],
        11: [[4, 101, 81]],
        12: [[2, 116, 92], [2, 117, 93]],
        13: [[4, 133, 107]],
        14: [[3, 145, 115], [1, 146, 116]],
        15: [[5, 109, 87], [1, 110, 88]],
        16: [[5, 122, 98], [1, 123, 99]],
        17: [[1, 135, 107], [5, 136, 108]],
        18: [[5, 150, 120], [1, 151, 121]],
        19: [[3, 141, 113], [4, 142, 114]],
        20: [[3, 135, 107], [5, 136, 108]]
    };

    var ALIGNMENT = {
        1: [],
        2: [6, 18],
        3: [6, 22],
        4: [6, 26],
        5: [6, 30],
        6: [6, 34],
        7: [6, 22, 38],
        8: [6, 24, 42],
        9: [6, 26, 46],
        10: [6, 28, 50],
        11: [6, 30, 54],
        12: [6, 32, 58],
        13: [6, 34, 62],
        14: [6, 26, 46, 66],
        15: [6, 26, 48, 70],
        16: [6, 26, 50, 74],
        17: [6, 30, 54, 78],
        18: [6, 30, 56, 82],
        19: [6, 30, 58, 86],
        20: [6, 34, 62, 90]
    };

    var GF_EXP = new Array(512);
    var GF_LOG = new Array(256);

    function initGaloisField() {
        var x = 1;
        var i;
        for (i = 0; i < 255; i += 1) {
            GF_EXP[i] = x;
            GF_LOG[x] = i;
            x <<= 1;
            if ((x & 0x100) !== 0) {
                x ^= 0x11d;
            }
        }
        for (i = 255; i < 512; i += 1) {
            GF_EXP[i] = GF_EXP[i - 255];
        }
        GF_LOG[0] = 0;
    }
    initGaloisField();

    function gfMultiply(left, right) {
        if (left === 0 || right === 0) {
            return 0;
        }
        return GF_EXP[GF_LOG[left] + GF_LOG[right]];
    }

    function polynomialMultiply(left, right) {
        var output = new Array(left.length + right.length - 1).fill(0);
        var i;
        var j;
        for (i = 0; i < left.length; i += 1) {
            for (j = 0; j < right.length; j += 1) {
                output[i + j] ^= gfMultiply(left[i], right[j]);
            }
        }
        return output;
    }

    function generatorPolynomial(degree) {
        var polynomial = [1];
        var i;
        for (i = 0; i < degree; i += 1) {
            polynomial = polynomialMultiply(polynomial, [1, GF_EXP[i]]);
        }
        return polynomial;
    }

    function reedSolomon(data, eccLength) {
        var generator = generatorPolynomial(eccLength);
        var remainder = new Array(eccLength).fill(0);
        var i;
        var j;
        var factor;
        for (i = 0; i < data.length; i += 1) {
            factor = data[i] ^ remainder[0];
            remainder.shift();
            remainder.push(0);
            if (factor === 0) {
                continue;
            }
            for (j = 0; j < eccLength; j += 1) {
                remainder[j] ^= gfMultiply(generator[j + 1], factor);
            }
        }
        return remainder;
    }

    function utf8Bytes(value) {
        var bytes = [];
        var i;
        var code;
        var next;
        for (i = 0; i < value.length; i += 1) {
            code = value.charCodeAt(i);
            if (code < 0x80) {
                bytes.push(code);
            } else if (code < 0x800) {
                bytes.push(0xc0 | (code >> 6), 0x80 | (code & 0x3f));
            } else if (code >= 0xd800 && code <= 0xdbff && i + 1 < value.length) {
                next = value.charCodeAt(i + 1);
                if (next >= 0xdc00 && next <= 0xdfff) {
                    code = 0x10000 + ((code - 0xd800) << 10) + (next - 0xdc00);
                    bytes.push(
                        0xf0 | (code >> 18),
                        0x80 | ((code >> 12) & 0x3f),
                        0x80 | ((code >> 6) & 0x3f),
                        0x80 | (code & 0x3f)
                    );
                    i += 1;
                } else {
                    bytes.push(0xef, 0xbf, 0xbd);
                }
            } else {
                bytes.push(
                    0xe0 | (code >> 12),
                    0x80 | ((code >> 6) & 0x3f),
                    0x80 | (code & 0x3f)
                );
            }
        }
        return bytes;
    }

    function expandBlocks(version) {
        var specification = RS_BLOCKS_L[version];
        var blocks = [];
        var group;
        var count;
        var i;
        if (!specification) {
            throw new Error('Unsupported QR version.');
        }
        for (i = 0; i < specification.length; i += 1) {
            group = specification[i];
            for (count = 0; count < group[0]; count += 1) {
                blocks.push({ total: group[1], data: group[2] });
            }
        }
        return blocks;
    }

    function totalDataCodewords(version) {
        return expandBlocks(version).reduce(function (sum, block) {
            return sum + block.data;
        }, 0);
    }

    function chooseVersion(byteLength) {
        var version;
        var lengthBits;
        var requiredBits;
        for (version = 1; version <= 20; version += 1) {
            lengthBits = version < 10 ? 8 : 16;
            if ((lengthBits === 8 && byteLength > 255) || byteLength > 65535) {
                continue;
            }
            requiredBits = 4 + lengthBits + (byteLength * 8);
            if (requiredBits <= totalDataCodewords(version) * 8) {
                return version;
            }
        }
        throw new Error('QR data is too long.');
    }

    function appendBits(target, value, length) {
        var i;
        for (i = length - 1; i >= 0; i -= 1) {
            target.push(((value >>> i) & 1) !== 0 ? 1 : 0);
        }
    }

    function dataCodewords(bytes, version) {
        var capacity = totalDataCodewords(version);
        var bits = [];
        var lengthBits = version < 10 ? 8 : 16;
        var output = [];
        var i;
        var value;
        var terminator;

        appendBits(bits, 0x4, 4); // Byte mode.
        appendBits(bits, bytes.length, lengthBits);
        for (i = 0; i < bytes.length; i += 1) {
            appendBits(bits, bytes[i], 8);
        }

        terminator = Math.min(4, (capacity * 8) - bits.length);
        for (i = 0; i < terminator; i += 1) {
            bits.push(0);
        }
        while ((bits.length % 8) !== 0) {
            bits.push(0);
        }

        for (i = 0; i < bits.length; i += 8) {
            value = 0;
            value |= bits[i] << 7;
            value |= bits[i + 1] << 6;
            value |= bits[i + 2] << 5;
            value |= bits[i + 3] << 4;
            value |= bits[i + 4] << 3;
            value |= bits[i + 5] << 2;
            value |= bits[i + 6] << 1;
            value |= bits[i + 7];
            output.push(value);
        }

        value = 0xec;
        while (output.length < capacity) {
            output.push(value);
            value = value === 0xec ? 0x11 : 0xec;
        }
        return output;
    }

    function finalCodewords(data, version) {
        var blockSpecs = expandBlocks(version);
        var dataBlocks = [];
        var eccBlocks = [];
        var offset = 0;
        var maxData = 0;
        var maxEcc = 0;
        var output = [];
        var i;
        var block;
        var eccLength;
        var row;

        for (i = 0; i < blockSpecs.length; i += 1) {
            block = blockSpecs[i];
            dataBlocks[i] = data.slice(offset, offset + block.data);
            offset += block.data;
            eccLength = block.total - block.data;
            eccBlocks[i] = reedSolomon(dataBlocks[i], eccLength);
            maxData = Math.max(maxData, dataBlocks[i].length);
            maxEcc = Math.max(maxEcc, eccBlocks[i].length);
        }

        for (row = 0; row < maxData; row += 1) {
            for (i = 0; i < dataBlocks.length; i += 1) {
                if (row < dataBlocks[i].length) {
                    output.push(dataBlocks[i][row]);
                }
            }
        }
        for (row = 0; row < maxEcc; row += 1) {
            for (i = 0; i < eccBlocks.length; i += 1) {
                if (row < eccBlocks[i].length) {
                    output.push(eccBlocks[i][row]);
                }
            }
        }
        return output;
    }

    function emptyMatrix(size) {
        var matrix = new Array(size);
        var row;
        for (row = 0; row < size; row += 1) {
            matrix[row] = new Array(size).fill(null);
        }
        return matrix;
    }

    function placeFinder(matrix, row, col) {
        var size = matrix.length;
        var r;
        var c;
        var rr;
        var cc;
        for (r = -1; r <= 7; r += 1) {
            rr = row + r;
            if (rr < 0 || rr >= size) {
                continue;
            }
            for (c = -1; c <= 7; c += 1) {
                cc = col + c;
                if (cc < 0 || cc >= size) {
                    continue;
                }
                matrix[rr][cc] = (
                    (r >= 0 && r <= 6 && (c === 0 || c === 6))
                    || (c >= 0 && c <= 6 && (r === 0 || r === 6))
                    || (r >= 2 && r <= 4 && c >= 2 && c <= 4)
                );
            }
        }
    }

    function placeAlignment(matrix, version) {
        var positions = ALIGNMENT[version] || [];
        var i;
        var j;
        var row;
        var col;
        var r;
        var c;
        for (i = 0; i < positions.length; i += 1) {
            for (j = 0; j < positions.length; j += 1) {
                row = positions[i];
                col = positions[j];
                if (matrix[row][col] !== null) {
                    continue;
                }
                for (r = -2; r <= 2; r += 1) {
                    for (c = -2; c <= 2; c += 1) {
                        matrix[row + r][col + c] = (
                            r === -2 || r === 2 || c === -2 || c === 2 || (r === 0 && c === 0)
                        );
                    }
                }
            }
        }
    }

    function placeTiming(matrix) {
        var size = matrix.length;
        var i;
        for (i = 8; i < size - 8; i += 1) {
            if (matrix[i][6] === null) {
                matrix[i][6] = (i % 2) === 0;
            }
            if (matrix[6][i] === null) {
                matrix[6][i] = (i % 2) === 0;
            }
        }
    }

    function bchRemainder(value, polynomial) {
        var polyDegree = 0;
        var temp = polynomial;
        var valueDegree;
        while (temp > 1) {
            polyDegree += 1;
            temp >>>= 1;
        }
        value <<= polyDegree;
        while (true) {
            valueDegree = -1;
            temp = value;
            while (temp > 0) {
                valueDegree += 1;
                temp >>>= 1;
            }
            if (valueDegree < polyDegree) {
                break;
            }
            value ^= polynomial << (valueDegree - polyDegree);
        }
        return value;
    }

    function formatBits(mask) {
        var data = (1 << 3) | mask; // Error correction L = binary 01.
        var remainder = bchRemainder(data, 0x537);
        return (((data << 10) | remainder) ^ 0x5412) & 0x7fff;
    }

    function versionBits(version) {
        var remainder = bchRemainder(version, 0x1f25);
        return ((version << 12) | remainder) & 0x3ffff;
    }

    function placeFormat(matrix, mask) {
        var size = matrix.length;
        var bits = formatBits(mask);
        var i;
        var dark;
        var row;
        var col;

        for (i = 0; i < 15; i += 1) {
            dark = ((bits >>> i) & 1) === 1;
            if (i < 6) {
                row = i;
            } else if (i < 8) {
                row = i + 1;
            } else {
                row = size - 15 + i;
            }
            matrix[row][8] = dark;
        }

        for (i = 0; i < 15; i += 1) {
            dark = ((bits >>> i) & 1) === 1;
            if (i < 8) {
                col = size - i - 1;
            } else if (i === 8) {
                col = 7;
            } else {
                col = 15 - i - 1;
            }
            matrix[8][col] = dark;
        }
        matrix[size - 8][8] = true;
    }

    function placeVersion(matrix, version) {
        var size;
        var bits;
        var i;
        var dark;
        if (version < 7) {
            return;
        }
        size = matrix.length;
        bits = versionBits(version);
        for (i = 0; i < 18; i += 1) {
            dark = ((bits >>> i) & 1) === 1;
            matrix[Math.floor(i / 3)][(i % 3) + size - 11] = dark;
            matrix[(i % 3) + size - 11][Math.floor(i / 3)] = dark;
        }
    }

    function maskApplies(mask, row, col) {
        switch (mask) {
        case 0: return ((row + col) % 2) === 0;
        case 1: return (row % 2) === 0;
        case 2: return (col % 3) === 0;
        case 3: return ((row + col) % 3) === 0;
        case 4: return ((Math.floor(row / 2) + Math.floor(col / 3)) % 2) === 0;
        case 5: return (((row * col) % 2) + ((row * col) % 3)) === 0;
        case 6: return ((((row * col) % 2) + ((row * col) % 3)) % 2) === 0;
        case 7: return ((((row * col) % 3) + ((row + col) % 2)) % 2) === 0;
        default: throw new Error('Invalid QR mask.');
        }
    }

    function placeData(matrix, codewords, mask) {
        var size = matrix.length;
        var bitLength = codewords.length * 8;
        var bitIndex = 0;
        var upward = true;
        var col;
        var rowOffset;
        var row;
        var c;
        var dark;

        for (col = size - 1; col > 0; col -= 2) {
            if (col === 6) {
                col -= 1;
            }
            for (rowOffset = 0; rowOffset < size; rowOffset += 1) {
                row = upward ? size - 1 - rowOffset : rowOffset;
                for (c = 0; c < 2; c += 1) {
                    if (matrix[row][col - c] !== null) {
                        continue;
                    }
                    dark = false;
                    if (bitIndex < bitLength) {
                        dark = ((codewords[Math.floor(bitIndex / 8)] >>> (7 - (bitIndex % 8))) & 1) === 1;
                        bitIndex += 1;
                    }
                    if (maskApplies(mask, row, col - c)) {
                        dark = !dark;
                    }
                    matrix[row][col - c] = dark;
                }
            }
            upward = !upward;
        }
    }

    function baseMatrix(version, mask) {
        var size = 17 + (version * 4);
        var matrix = emptyMatrix(size);
        placeFinder(matrix, 0, 0);
        placeFinder(matrix, size - 7, 0);
        placeFinder(matrix, 0, size - 7);
        placeAlignment(matrix, version);
        placeTiming(matrix);
        placeFormat(matrix, mask);
        placeVersion(matrix, version);
        return matrix;
    }

    function penalty(matrix) {
        var size = matrix.length;
        var total = size * size;
        var darkCount = 0;
        var score = 0;
        var row;
        var col;
        var runColor;
        var runLength;
        var sequence;
        var i;

        function addRun(length) {
            if (length >= 5) {
                score += 3 + (length - 5);
            }
        }

        for (row = 0; row < size; row += 1) {
            runColor = matrix[row][0];
            runLength = 1;
            for (col = 0; col < size; col += 1) {
                if (matrix[row][col]) {
                    darkCount += 1;
                }
                if (col === 0) {
                    continue;
                }
                if (matrix[row][col] === runColor) {
                    runLength += 1;
                } else {
                    addRun(runLength);
                    runColor = matrix[row][col];
                    runLength = 1;
                }
            }
            addRun(runLength);
        }

        for (col = 0; col < size; col += 1) {
            runColor = matrix[0][col];
            runLength = 1;
            for (row = 1; row < size; row += 1) {
                if (matrix[row][col] === runColor) {
                    runLength += 1;
                } else {
                    addRun(runLength);
                    runColor = matrix[row][col];
                    runLength = 1;
                }
            }
            addRun(runLength);
        }

        for (row = 0; row < size - 1; row += 1) {
            for (col = 0; col < size - 1; col += 1) {
                if (matrix[row][col] === matrix[row][col + 1]
                    && matrix[row][col] === matrix[row + 1][col]
                    && matrix[row][col] === matrix[row + 1][col + 1]) {
                    score += 3;
                }
            }
        }

        for (row = 0; row < size; row += 1) {
            sequence = '';
            for (col = 0; col < size; col += 1) {
                sequence += matrix[row][col] ? '1' : '0';
            }
            for (i = 0; i <= sequence.length - 11; i += 1) {
                if (sequence.substr(i, 11) === '00001011101' || sequence.substr(i, 11) === '10111010000') {
                    score += 40;
                }
            }
        }
        for (col = 0; col < size; col += 1) {
            sequence = '';
            for (row = 0; row < size; row += 1) {
                sequence += matrix[row][col] ? '1' : '0';
            }
            for (i = 0; i <= sequence.length - 11; i += 1) {
                if (sequence.substr(i, 11) === '00001011101' || sequence.substr(i, 11) === '10111010000') {
                    score += 40;
                }
            }
        }

        score += Math.floor(Math.abs((darkCount * 100 / total) - 50) / 5) * 10;
        return score;
    }

    function makeMatrix(value) {
        var bytes;
        var version;
        var data;
        var codewords;
        var best = null;
        var bestPenalty = Infinity;
        var mask;
        var matrix;
        var currentPenalty;

        if (typeof value !== 'string' || value.length === 0 || value.length > 2048) {
            throw new Error('QR data is invalid.');
        }
        bytes = utf8Bytes(value);
        version = chooseVersion(bytes.length);
        data = dataCodewords(bytes, version);
        codewords = finalCodewords(data, version);

        for (mask = 0; mask < 8; mask += 1) {
            matrix = baseMatrix(version, mask);
            placeData(matrix, codewords, mask);
            currentPenalty = penalty(matrix);
            if (currentPenalty < bestPenalty) {
                bestPenalty = currentPenalty;
                best = matrix;
            }
        }
        return best;
    }

    function render(target, value) {
        var matrix;
        var quiet = 4;
        var size;
        var svg;
        var background;
        var path;
        var data = '';
        var row;
        var col;
        var namespace = 'http://www.w3.org/2000/svg';

        if (!target || !target.ownerDocument) {
            throw new Error('QR target is invalid.');
        }
        matrix = makeMatrix(value);
        size = matrix.length + (quiet * 2);
        while (target.firstChild) {
            target.removeChild(target.firstChild);
        }

        svg = target.ownerDocument.createElementNS(namespace, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
        svg.setAttribute('width', '256');
        svg.setAttribute('height', '256');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'Authenticator登録用QRコード');
        svg.setAttribute('shape-rendering', 'crispEdges');
        svg.style.maxWidth = '100%';
        svg.style.height = 'auto';
        svg.style.backgroundColor = '#fff';

        background = target.ownerDocument.createElementNS(namespace, 'rect');
        background.setAttribute('width', String(size));
        background.setAttribute('height', String(size));
        background.setAttribute('fill', '#fff');
        svg.appendChild(background);

        for (row = 0; row < matrix.length; row += 1) {
            for (col = 0; col < matrix.length; col += 1) {
                if (matrix[row][col]) {
                    data += 'M' + (col + quiet) + ' ' + (row + quiet) + 'h1v1h-1z';
                }
            }
        }
        path = target.ownerDocument.createElementNS(namespace, 'path');
        path.setAttribute('d', data);
        path.setAttribute('fill', '#000');
        svg.appendChild(path);
        target.appendChild(svg);
    }

    var api = {
        makeMatrix: makeMatrix,
        render: render
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
    if (root) {
        root.iGuguruTotpQr = api;
    }
}(typeof window !== 'undefined' ? window : null));
