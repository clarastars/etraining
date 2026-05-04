const CHUNK_SIZE = 8 * 1024 * 1024;

/** Files larger than this use chunked HTTP uploads before the Inertia save. */
const CHUNK_THRESHOLD = 4 * 1024 * 1024;

function ziggyRoute(name, params = {}) {
  if (typeof window !== "undefined" && typeof window.route === "function") {
    return window.route(name, params);
  }
  throw new Error("Ziggy route() is not available.");
}

export function needsChunkedRecordedCourseVideoUpload(file) {
  return file instanceof File && file.size > CHUNK_THRESHOLD;
}

/**
 * @param {File} file
 * @param {(fraction: number) => void} [onProgress] 0–1 across the full file
 * @returns {Promise<string>} upload_token for lessons[].upload_token
 */
export async function uploadRecordedCourseLessonVideoInChunks(file, onProgress) {
  const routeStart = ziggyRoute(
    "back.settings.recorded-courses.lesson-videos.chunk-uploads.start"
  );
  const { data: started } = await window.axios.post(routeStart, {
    file_name: file.name,
    mime_type: file.type || "application/octet-stream",
    total_size: file.size,
  });
  const uploadId = started.upload_id;
  const total = file.size;
  let offset = 0;
  let chunkIndex = 0;

  while (offset < total) {
    const end = Math.min(offset + CHUNK_SIZE, total);
    const slice = file.slice(offset, end);
    const buffer = await slice.arrayBuffer();
    const chunkUrl = ziggyRoute(
      "back.settings.recorded-courses.lesson-videos.chunk-uploads.chunk",
      uploadId
    );
    await window.axios.post(chunkUrl, buffer, {
      headers: {
        "Content-Type": "application/octet-stream",
        "X-Chunk-Index": String(chunkIndex),
      },
      onUploadProgress: (evt) => {
        if (typeof onProgress === "function") {
          const loaded = offset + (evt.loaded || 0);
          onProgress(Math.min(1, loaded / total));
        }
      },
    });
    offset = end;
    chunkIndex += 1;
  }

  const completeUrl = ziggyRoute(
    "back.settings.recorded-courses.lesson-videos.chunk-uploads.complete",
    uploadId
  );
  const { data: done } = await window.axios.post(completeUrl);
  if (typeof onProgress === "function") {
    onProgress(1);
  }
  return done.upload_token;
}
