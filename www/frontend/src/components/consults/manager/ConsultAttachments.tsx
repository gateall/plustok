import { useCallback, useEffect, useState } from 'react';

import { AttachmentList, Upload, type UploadProgress } from '@/components/admin-ui';

import type { ConsultAttachment } from '@/types/consult.types';

import { consultService } from '@/services/consult.service';

import toast from 'react-hot-toast';



type ConsultAttachmentsProps = {

  consultId: string;

};



const MAX_BYTES = 10 * 1024 * 1024;



export default function ConsultAttachments({ consultId }: ConsultAttachmentsProps) {

  const [files, setFiles] = useState<ConsultAttachment[]>([]);

  const [loading, setLoading] = useState(true);

  const [uploading, setUploading] = useState(false);

  const [pending, setPending] = useState(false);

  const [progress, setProgress] = useState<UploadProgress[]>([]);



  const loadFiles = useCallback(async () => {

    setLoading(true);

    try {

      const { data, pending: isPending } = await consultService.listAttachments(consultId);

      setFiles(data);

      setPending(isPending);

    } catch {

      setFiles([]);

      setPending(true);

    } finally {

      setLoading(false);

    }

  }, [consultId]);



  useEffect(() => {

    void loadFiles();

  }, [loadFiles]);



  const uploadFiles = async (fileList: File[]) => {

    const valid = fileList.filter((f) => {

      if (f.size > MAX_BYTES) {

        toast.error(`${f.name}: 10MB 초과`);

        return false;

      }

      return true;

    });

    if (valid.length === 0) return;



    setUploading(true);

    setProgress(

      valid.map((f) => ({ fileName: f.name, progress: 0, status: 'pending' as const })),

    );



    let anyPending = false;



    for (const file of valid) {

      setProgress((prev) =>

        prev.map((p) =>

          p.fileName === file.name ? { ...p, status: 'uploading', progress: 30 } : p,

        ),

      );



      try {

        const { data, pending: isPending } = await consultService.uploadAttachment(consultId, file);

        setFiles((prev) => [...prev, data]);

        if (isPending) anyPending = true;

        setProgress((prev) =>

          prev.map((p) =>

            p.fileName === file.name ? { ...p, status: 'done', progress: 100 } : p,

          ),

        );

      } catch {

        setProgress((prev) =>

          prev.map((p) =>

            p.fileName === file.name ? { ...p, status: 'error', progress: 100 } : p,

          ),

        );

        toast.error(`${file.name} 업로드 실패`);

      }

    }



    setUploading(false);

    setTimeout(() => setProgress([]), 1500);



    if (anyPending) {

      toast('파일 API 연동 대기 중 (Codex)', { icon: 'ℹ️' });

    } else if (valid.length > 0) {

      toast.success(`${valid.length}개 파일 업로드됨`);

    }

  };



  const removeFile = async (attachment: ConsultAttachment) => {

    try {

      const { pending: isPending } = await consultService.deleteAttachment(consultId, attachment.id);

      setFiles((prev) => prev.filter((f) => f.id !== attachment.id));

      if (isPending) {

        toast('삭제 API 연동 대기 — UI에서만 제거됨', { icon: 'ℹ️' });

      } else {

        toast.success('파일 삭제됨');

      }

    } catch {

      toast.error('파일 삭제 실패');

    }

  };



  return (

    <div className="consult-attachments space-y-3 p-4">

      <Upload

        uploading={uploading}

        progress={progress}

        onFilesSelected={uploadFiles}

        maxBytes={MAX_BYTES}

      />



      <AttachmentList

        items={files}

        loading={loading}

        onDelete={removeFile}

        downloadHref={(item) =>

          item.url ?? consultService.downloadUrl(consultId, item.id)

        }

      />



      {pending ? (

        <p className="text-center text-xs text-amber-600">API pending — Codex 파일 API 연동 대기</p>

      ) : null}

    </div>

  );

}

