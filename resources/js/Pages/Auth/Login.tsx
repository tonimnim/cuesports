import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertCircle } from 'lucide-react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/dashboard/login');
    };

    return (
        <>
            <Head title="Login - Dashboard" />

            <div className="min-h-screen relative overflow-hidden">
                {/* Diagonal gradient background */}
                <div
                    className="absolute inset-0"
                    style={{
                        background: `linear-gradient(135deg, #004E86 0%, #003D6B 40%, #C9A227 100%)`,
                    }}
                />

                {/* Diagonal cut overlay */}
                <div
                    className="absolute inset-0"
                    style={{
                        background: `linear-gradient(165deg, transparent 45%, #F8FAFC 45.5%)`,
                    }}
                />

                {/* Content */}
                <div className="relative z-10 min-h-screen flex items-center justify-center p-6">
                    <div className="w-full max-w-md">
                        {/* Logo and branding */}
                        <div className="text-center mb-8">
                            <div className="flex justify-center mb-4">
                                <div className="flex size-16 items-center justify-center rounded-2xl bg-white shadow-lg">
                                    <img src="/logo.svg" alt="CueSports Africa" className="size-10" />
                                </div>
                            </div>
                            <h1 className="text-2xl font-bold text-[#0A1628]">
                                CueSports Africa
                            </h1>
                            <p className="text-[#64748B] mt-1">
                                Admin & Support Dashboard
                            </p>
                        </div>

                        {/* Login card */}
                        <Card className="shadow-2xl border-0">
                            <CardHeader className="text-center pb-2">
                                <CardTitle className="text-xl text-[#0A1628]">Welcome back</CardTitle>
                                <CardDescription className="text-[#64748B]">
                                    Sign in to continue
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <form onSubmit={handleSubmit} className="space-y-5">
                                    {errors.email && (
                                        <div className="flex items-center gap-2 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-600">
                                            <AlertCircle className="size-4 shrink-0" />
                                            {errors.email}
                                        </div>
                                    )}

                                    <div className="space-y-2">
                                        <Label htmlFor="email" className="text-[#0A1628]">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            placeholder="you@cuesportsafrica.com"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            autoComplete="email"
                                            autoFocus
                                            className="h-11 bg-white border-[#E2E8F0] focus:border-[#004E86] focus:ring-[#004E86]"
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="password" className="text-[#0A1628]">Password</Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            placeholder="••••••••"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            autoComplete="current-password"
                                            className="h-11 bg-white border-[#E2E8F0] focus:border-[#004E86] focus:ring-[#004E86]"
                                        />
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <input
                                            id="remember"
                                            type="checkbox"
                                            className="size-4 rounded border-[#E2E8F0] text-[#004E86] focus:ring-[#004E86]"
                                            checked={data.remember}
                                            onChange={(e) => setData('remember', e.target.checked)}
                                        />
                                        <Label htmlFor="remember" className="text-sm font-normal text-[#64748B]">
                                            Remember me
                                        </Label>
                                    </div>

                                    <Button
                                        type="submit"
                                        className="w-full h-11 bg-[#004E86] hover:bg-[#003D6B] text-white font-medium"
                                        disabled={processing}
                                    >
                                        {processing ? 'Signing in...' : 'Sign in'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
