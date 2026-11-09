<?php

namespace App\Filament\Widgets;

use App\Models\Announcement;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Page;
use App\Models\ServiceTerm;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeMeetings = Meeting::where('is_active', true)->count();
        $totalMeetings = Meeting::count();
        $totalGroups = Group::count();
        $publishedPages = Page::where('is_published', true)->count();
        $activeAnnouncements = Announcement::where('is_active', true)->count();
        $activeTerms = ServiceTerm::where('end_date', '>=', now()->toDateString())->count();

        return [
            Stat::make('Active Meetings', (string) $activeMeetings)
                ->description("{$totalMeetings} total in directory")
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),
            Stat::make('Fellowship Groups', (string) $totalGroups)
                ->description('Registered groups across Egypt')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
            Stat::make('Published Pages', (string) $publishedPages)
                ->description('Bilingual content pages')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),
            Stat::make('Active Announcements', (string) $activeAnnouncements)
                ->description('Published banners')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('warning'),
            Stat::make('Active Service Terms', (string) $activeTerms)
                ->description('Current trusted servants')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('gray'),
        ];
    }
}
