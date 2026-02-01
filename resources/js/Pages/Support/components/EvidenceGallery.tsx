import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Image as ImageIcon, Video, ZoomIn, ExternalLink } from 'lucide-react';
import type { MatchEvidence } from '@/types';

interface EvidenceGalleryProps {
    evidence: MatchEvidence[];
}

export function EvidenceGallery({ evidence }: EvidenceGalleryProps) {
    const [selectedImage, setSelectedImage] = useState<MatchEvidence | null>(null);

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getEvidenceLabel = (type: string) => {
        switch (type) {
            case 'score_proof':
                return 'Score Proof';
            case 'dispute_evidence':
                return 'Dispute Evidence';
            default:
                return 'Other';
        }
    };

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center justify-between">
                        <span className="flex items-center gap-2">
                            <ImageIcon className="size-5 text-[#004E86]" />
                            Evidence & Proof
                        </span>
                        <Badge variant="secondary">{evidence.length} files</Badge>
                    </CardTitle>
                    <CardDescription>
                        Photos and videos submitted by players as proof
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {evidence.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center rounded-lg border-2 border-dashed">
                            <ImageIcon className="size-12 text-muted-foreground/50 mb-3" />
                            <p className="text-sm font-medium text-muted-foreground">
                                No evidence submitted
                            </p>
                            <p className="text-xs text-muted-foreground mt-1">
                                Players have not uploaded any proof for this match
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            {evidence.map((item) => (
                                <div
                                    key={item.id}
                                    className="group relative aspect-square rounded-lg overflow-hidden border bg-slate-100 cursor-pointer transition-all hover:ring-2 hover:ring-[#004E86] hover:ring-offset-2"
                                    onClick={() => setSelectedImage(item)}
                                >
                                    {item.file_type === 'image' ? (
                                        <img
                                            src={item.thumbnail_url || item.file_url}
                                            alt={item.description || 'Evidence'}
                                            className="w-full h-full object-cover"
                                        />
                                    ) : (
                                        <div className="w-full h-full flex items-center justify-center bg-slate-200">
                                            <Video className="size-12 text-slate-400" />
                                        </div>
                                    )}
                                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-colors flex items-center justify-center">
                                        <ZoomIn className="size-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                    </div>
                                    <div className="absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/70 to-transparent">
                                        <p className="text-xs text-white truncate">
                                            {item.uploader?.name || 'Unknown'}
                                        </p>
                                        <Badge variant="secondary" className="text-[10px] mt-1 bg-white/20 text-white">
                                            {getEvidenceLabel(item.evidence_type)}
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Lightbox Dialog */}
            <Dialog open={!!selectedImage} onOpenChange={() => setSelectedImage(null)}>
                <DialogContent className="max-w-4xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            {selectedImage?.file_type === 'image' ? (
                                <ImageIcon className="size-5" />
                            ) : (
                                <Video className="size-5" />
                            )}
                            Evidence from {selectedImage?.uploader?.name || 'Unknown'}
                        </DialogTitle>
                        <DialogDescription>
                            {getEvidenceLabel(selectedImage?.evidence_type || '')} — Uploaded{' '}
                            {formatDate(selectedImage?.uploaded_at || null)}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="relative aspect-video bg-slate-100 rounded-lg overflow-hidden">
                        {selectedImage?.file_type === 'image' ? (
                            <img
                                src={selectedImage.file_url}
                                alt={selectedImage.description || 'Evidence'}
                                className="w-full h-full object-contain"
                            />
                        ) : (
                            <video src={selectedImage?.file_url} controls className="w-full h-full" />
                        )}
                    </div>
                    {selectedImage?.description && (
                        <p className="text-sm text-muted-foreground">{selectedImage.description}</p>
                    )}
                    <div className="flex justify-end">
                        <Button variant="outline" asChild>
                            <a href={selectedImage?.file_url} target="_blank" rel="noopener noreferrer">
                                <ExternalLink className="size-4 mr-2" />
                                Open Original
                            </a>
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
