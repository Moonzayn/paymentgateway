package com.payment.app.ui.main

import android.content.Intent
import android.os.Bundle
import android.view.Menu
import android.view.MenuItem
import android.view.View
import android.widget.TextView
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import com.google.android.material.navigation.NavigationView
import com.payment.app.R
import com.payment.app.databinding.ActivityMainBinding
import com.payment.app.ui.auth.LoginActivity
import com.payment.app.ui.chat.ChatActivity
import com.payment.app.ui.deposit.DepositActivity
import com.payment.app.ui.pos.PosActivity
import com.payment.app.util.Resource
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class MainActivity : AppCompatActivity(), NavigationView.OnNavigationItemSelectedListener {

    private lateinit var binding: ActivityMainBinding
    private val viewModel: MainViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setSupportActionBar(binding.toolbar)
        setupViews()
        observeData()
    }

    private fun setupViews() {
        binding.navView.setNavigationItemSelectedListener(this)

        binding.swipeRefresh.setOnRefreshListener {
            viewModel.refreshData()
        }

        binding.cardPos.setOnClickListener {
            startActivity(Intent(this, PosActivity::class.java))
        }

        binding.cardDeposit.setOnClickListener {
            startActivity(Intent(this, DepositActivity::class.java))
        }

        binding.cardChat.setOnClickListener {
            startActivity(Intent(this, ChatActivity::class.java))
        }

        // Update header
        val headerView = binding.navView.getHeaderView(0)
        val tvUserName = headerView.findViewById<TextView>(R.id.tv_user_name)
        tvUserName.text = viewModel.getUserName()
    }

    private fun observeData() {
        viewModel.saldo.observe(this) { state ->
            when (state) {
                is Resource.Loading -> {
                    binding.progressBar.visibility = View.VISIBLE
                }
                is Resource.Success -> {
                    binding.progressBar.visibility = View.GONE
                    binding.swipeRefresh.isRefreshing = false

                    state.data?.let { data ->
                        binding.tvSaldo.text = data.saldoDisplay
                    }
                }
                is Resource.Error -> {
                    binding.progressBar.visibility = View.GONE
                    binding.swipeRefresh.isRefreshing = false
                    Toast.makeText(this, state.message, Toast.LENGTH_SHORT).show()
                }
            }
        }

        viewModel.notifications.observe(this) { state ->
            when (state) {
                is Resource.Success -> {
                    state.data?.let { data ->
                        // Handle notification badge through menu
                        val menu = binding.navView.menu
                        val notifItem = menu.findItem(R.id.nav_notifications)
                        if (data.unread > 0) {
                            notifItem?.title = "Notifikasi (${data.unread})"
                        } else {
                            notifItem?.title = "Notifikasi"
                        }

                        val chatItem = menu.findItem(R.id.nav_chat)
                        if (data.chatUnread > 0) {
                            chatItem?.title = "Chat (${data.chatUnread})"
                        } else {
                            chatItem?.title = "Chat"
                        }
                    }
                }
                else -> {}
            }
        }

        viewModel.logout.observe(this) { loggedOut ->
            if (loggedOut) {
                startActivity(Intent(this, LoginActivity::class.java))
                finish()
            }
        }
    }

    override fun onNavigationItemSelected(item: MenuItem): Boolean {
        when (item.itemId) {
            R.id.nav_pos -> {
                startActivity(Intent(this, PosActivity::class.java))
            }
            R.id.nav_deposit -> {
                startActivity(Intent(this, DepositActivity::class.java))
            }
            R.id.nav_chat -> {
                startActivity(Intent(this, ChatActivity::class.java))
            }
            R.id.nav_logout -> {
                showLogoutDialog()
            }
        }
        return true
    }

    private fun showLogoutDialog() {
        AlertDialog.Builder(this)
            .setTitle("Logout")
            .setMessage("Apakah Anda yakin ingin logout?")
            .setPositiveButton("Ya") { _, _ ->
                viewModel.logout()
            }
            .setNegativeButton("Tidak", null)
            .show()
    }

    override fun onCreateOptionsMenu(menu: Menu): Boolean {
        menuInflater.inflate(R.menu.menu_main, menu)
        return true
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        return when (item.itemId) {
            R.id.action_refresh -> {
                viewModel.refreshData()
                true
            }
            else -> super.onOptionsItemSelected(item)
        }
    }

    override fun onResume() {
        super.onResume()
        viewModel.loadSaldo()
    }
}
