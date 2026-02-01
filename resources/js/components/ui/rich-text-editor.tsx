import { useState } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import { Button } from './button';
import { Input } from './input';
import { Label } from './label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from './dialog';
import {
    Bold,
    Italic,
    List,
    ListOrdered,
    Heading2,
    Heading3,
    Quote,
    Undo,
    Redo,
    Link as LinkIcon,
    Image as ImageIcon,
    Code,
    Minus,
    Unlink,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface RichTextEditorProps {
    content: string;
    onChange: (html: string) => void;
    placeholder?: string;
    className?: string;
}

export function RichTextEditor({ content, onChange, placeholder = 'Start writing...', className }: RichTextEditorProps) {
    const [imageDialogOpen, setImageDialogOpen] = useState(false);
    const [imageUrl, setImageUrl] = useState('');
    const [imageAlt, setImageAlt] = useState('');
    const [linkDialogOpen, setLinkDialogOpen] = useState(false);
    const [linkUrl, setLinkUrl] = useState('');

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: {
                    levels: [2, 3],
                },
            }),
            Link.configure({
                openOnClick: false,
                HTMLAttributes: {
                    class: 'text-primary underline',
                },
            }),
            Image.configure({
                HTMLAttributes: {
                    class: 'rounded-lg max-w-full my-4',
                },
            }),
            Placeholder.configure({
                placeholder,
            }),
        ],
        content,
        onUpdate: ({ editor }) => {
            onChange(editor.getHTML());
        },
        editorProps: {
            attributes: {
                class: 'prose prose-sm max-w-none focus:outline-none min-h-[300px] px-4 py-3',
            },
        },
    });

    if (!editor) {
        return null;
    }

    const openLinkDialog = () => {
        const previousUrl = editor.getAttributes('link').href || '';
        setLinkUrl(previousUrl);
        setLinkDialogOpen(true);
    };

    const addLink = () => {
        if (linkUrl) {
            editor.chain().focus().extendMarkRange('link').setLink({ href: linkUrl }).run();
        } else {
            editor.chain().focus().unsetLink().run();
        }
        setLinkDialogOpen(false);
        setLinkUrl('');
    };

    const removeLink = () => {
        editor.chain().focus().unsetLink().run();
        setLinkDialogOpen(false);
        setLinkUrl('');
    };

    const openImageDialog = () => {
        setImageUrl('');
        setImageAlt('');
        setImageDialogOpen(true);
    };

    const addImage = () => {
        if (imageUrl) {
            editor.chain().focus().setImage({ src: imageUrl, alt: imageAlt }).run();
        }
        setImageDialogOpen(false);
        setImageUrl('');
        setImageAlt('');
    };

    return (
        <>
            <div className={cn('border rounded-lg overflow-hidden bg-background', className)}>
                {/* Toolbar */}
                <div className="flex flex-wrap items-center gap-1 p-2 border-b bg-muted/50">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().toggleBold().run()}
                        className={cn('size-8 p-0', editor.isActive('bold') && 'bg-muted')}
                        title="Bold (Ctrl+B)"
                    >
                        <Bold className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().toggleItalic().run()}
                        className={cn('size-8 p-0', editor.isActive('italic') && 'bg-muted')}
                        title="Italic (Ctrl+I)"
                    >
                        <Italic className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().toggleCode().run()}
                        className={cn('size-8 p-0', editor.isActive('code') && 'bg-muted')}
                        title="Code"
                    >
                        <Code className="size-4" />
                    </Button>

                    <div className="w-px h-6 bg-border mx-1" />

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
                        className={cn('size-8 p-0', editor.isActive('heading', { level: 2 }) && 'bg-muted')}
                        title="Heading 2"
                    >
                        <Heading2 className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
                        className={cn('size-8 p-0', editor.isActive('heading', { level: 3 }) && 'bg-muted')}
                        title="Heading 3"
                    >
                        <Heading3 className="size-4" />
                    </Button>

                    <div className="w-px h-6 bg-border mx-1" />

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().toggleBulletList().run()}
                        className={cn('size-8 p-0', editor.isActive('bulletList') && 'bg-muted')}
                        title="Bullet List"
                    >
                        <List className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().toggleOrderedList().run()}
                        className={cn('size-8 p-0', editor.isActive('orderedList') && 'bg-muted')}
                        title="Numbered List"
                    >
                        <ListOrdered className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().toggleBlockquote().run()}
                        className={cn('size-8 p-0', editor.isActive('blockquote') && 'bg-muted')}
                        title="Quote"
                    >
                        <Quote className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().setHorizontalRule().run()}
                        className="size-8 p-0"
                        title="Horizontal Rule"
                    >
                        <Minus className="size-4" />
                    </Button>

                    <div className="w-px h-6 bg-border mx-1" />

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={openLinkDialog}
                        className={cn('size-8 p-0', editor.isActive('link') && 'bg-muted')}
                        title="Add Link"
                    >
                        <LinkIcon className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={openImageDialog}
                        className="size-8 p-0"
                        title="Insert Image"
                    >
                        <ImageIcon className="size-4" />
                    </Button>

                    <div className="flex-1" />

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().undo().run()}
                        disabled={!editor.can().undo()}
                        className="size-8 p-0"
                        title="Undo"
                    >
                        <Undo className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().redo().run()}
                        disabled={!editor.can().redo()}
                        className="size-8 p-0"
                        title="Redo"
                    >
                        <Redo className="size-4" />
                    </Button>
                </div>

                {/* Editor */}
                <EditorContent editor={editor} />
            </div>

            {/* Image Dialog */}
            <Dialog open={imageDialogOpen} onOpenChange={setImageDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Insert Image</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="image-url">Image URL</Label>
                            <Input
                                id="image-url"
                                value={imageUrl}
                                onChange={(e) => setImageUrl(e.target.value)}
                                placeholder="https://example.com/image.jpg"
                                type="url"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="image-alt">Alt Text (optional)</Label>
                            <Input
                                id="image-alt"
                                value={imageAlt}
                                onChange={(e) => setImageAlt(e.target.value)}
                                placeholder="Describe the image..."
                            />
                        </div>
                        {imageUrl && (
                            <div className="rounded-lg overflow-hidden bg-muted">
                                <img
                                    src={imageUrl}
                                    alt={imageAlt || 'Preview'}
                                    className="max-h-48 w-full object-contain"
                                    onError={(e) => {
                                        (e.target as HTMLImageElement).style.display = 'none';
                                    }}
                                />
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setImageDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={addImage} disabled={!imageUrl}>
                            Insert Image
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Link Dialog */}
            <Dialog open={linkDialogOpen} onOpenChange={setLinkDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editor.isActive('link') ? 'Edit Link' : 'Add Link'}</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="link-url">URL</Label>
                            <Input
                                id="link-url"
                                value={linkUrl}
                                onChange={(e) => setLinkUrl(e.target.value)}
                                placeholder="https://example.com"
                                type="url"
                            />
                        </div>
                    </div>
                    <DialogFooter className="flex-col sm:flex-row gap-2">
                        {editor.isActive('link') && (
                            <Button type="button" variant="destructive" onClick={removeLink} className="sm:mr-auto">
                                <Unlink className="size-4 mr-2" />
                                Remove Link
                            </Button>
                        )}
                        <Button type="button" variant="outline" onClick={() => setLinkDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={addLink}>
                            {editor.isActive('link') ? 'Update Link' : 'Add Link'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
