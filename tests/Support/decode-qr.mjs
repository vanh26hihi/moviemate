import { PNG } from 'pngjs';
import zxing from '@zxing/library';

const {
    BinaryBitmap,
    HybridBinarizer,
    MultiFormatReader,
    RGBLuminanceSource,
    DecodeHintType,
    BarcodeFormat,
} = zxing;

let encoded = '';
for await (const chunk of process.stdin) encoded += chunk;

const png = PNG.sync.read(Buffer.from(encoded.trim(), 'base64'));
const luminance = new Uint8ClampedArray(png.width * png.height);
for (let source = 0, target = 0; source < png.data.length; source += 4, target += 1) {
    luminance[target] = (png.data[source] + (2 * png.data[source + 1]) + png.data[source + 2]) / 4;
}

const bitmap = new BinaryBitmap(new HybridBinarizer(
    new RGBLuminanceSource(luminance, png.width, png.height),
));
const hints = new Map([[DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.QR_CODE]]]);
process.stdout.write(new MultiFormatReader().decode(bitmap, hints).getText());
