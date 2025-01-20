import { IncomingForm } from 'formidable';
import fs from 'fs-extra';
import path from 'path';

export const config = {
  api: {
    bodyParser: false,
  },
};

const uploadDir = path.join(process.cwd(), 'uploads');

fs.ensureDirSync(uploadDir);

export default async (req, res) => {
  if (req.method !== 'POST') {
    return res.status(405).json({ error: `Method '${req.method}' Not Allowed` });
  }

  const form = new IncomingForm({
    uploadDir,
    keepExtensions: true,
    maxFileSize: 100 * 1024 * 1024, 
    filename: (name, ext, part, form) => {
      return `${Date.now()}-${part.originalFilename}`;
    },
  });

  try {
    const files = await fs.readdir(uploadDir);
    await Promise.all(files.map(file => fs.unlink(path.join(uploadDir, file))));
  } catch (err) {
    console.error('Error clearing old uploads:', err);
  }

  form.parse(req, (err, fields, files) => {
    if (err) {
      console.error('Error in API route:', err);
      return res.status(500).json({ error: `Something went wrong! ${err.message}` });
    }

    if (!files.file) {
      return res.status(400).json({ error: 'No file uploaded.' });
    }

    res.status(200).json({
      message: 'File uploaded successfully.',
      filename: files.file.newFilename,
    });
  });
};
